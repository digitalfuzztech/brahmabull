<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatE2eeConversationKey;
use App\Models\ChatE2eeDevice;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class E2eeRotationService
{
    public function __construct(
        private readonly E2eeAuthorizationService $authorization,
        private readonly E2eeConversationKeyService $keys,
        private readonly E2eeDeviceProofService $proofs,
    ) {}

    public function rotate(
        ChatConversation $conversation,
        User $actor,
        string $initiatorDeviceUuid,
        int $keyVersion,
        array $wrappedKeys,
        string $signature,
    ): ChatConversation {
        return DB::transaction(function () use ($conversation, $actor, $initiatorDeviceUuid, $keyVersion, $wrappedKeys, $signature): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $participantIds = $this->authorization->assertDirectParticipant($locked, $actor);

            if ($locked->encryption_mode !== 'e2ee_v1' || $locked->e2ee_rotation_required_at === null) {
                throw new DomainException('This conversation does not currently require key rotation.');
            }
            if ($keyVersion !== ((int) $locked->current_key_version + 1)) {
                throw new DomainException('The rotation key version must be exactly the current version plus one.');
            }

            $initiator = ChatE2eeDevice::query()
                ->where('user_id', $actor->id)
                ->where('device_uuid', $initiatorDeviceUuid)
                ->whereNotNull('trusted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->firstOrFail();
            if (! ChatE2eeConversationKey::query()
                ->where('conversation_id', $locked->id)
                ->where('device_id', $initiator->id)
                ->where('key_version', $locked->current_key_version)
                ->exists()) {
                throw new AuthorizationException('The rotating device does not possess the current conversation key.');
            }

            $eligibleDevices = ChatE2eeDevice::query()
                ->whereIn('user_id', $participantIds)
                ->whereNotNull('trusted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            foreach ($participantIds as $participantId) {
                if (! $eligibleDevices->contains('user_id', $participantId)) {
                    throw new DomainException('Each participant requires an active trusted device before rotation can complete.');
                }
            }

            $normalized = $this->proofs->normalizedWrappedKeys($wrappedKeys);
            $providedIds = collect($normalized)->pluck('device_id')->all();
            $expectedIds = $eligibleDevices->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (count($providedIds) !== count(array_unique($providedIds)) || $providedIds !== $expectedIds) {
                throw new DomainException('Rotation requires exactly one wrapped key for every eligible participant device.');
            }
            if (ChatE2eeConversationKey::where('conversation_id', $locked->id)->where('key_version', $keyVersion)->exists()) {
                throw new DomainException('This conversation-key version is already established and cannot be replaced.');
            }

            $canonical = $this->proofs->rotationCanonical(
                $actor->id,
                $initiator->id,
                $locked->id,
                $keyVersion,
                $normalized,
            );
            $this->proofs->assertSignature($initiator, $canonical, $signature);

            foreach ($normalized as $item) {
                $this->keys->validateWrappedKey(
                    $item['wrapped_key'],
                    $item['wrapping_algorithm'],
                    $item['format_version'],
                );
                ChatE2eeConversationKey::create([
                    'conversation_id' => $locked->id,
                    'device_id' => $item['device_id'],
                    'key_version' => $keyVersion,
                    'wrapped_key' => $item['wrapped_key'],
                    'wrapping_algorithm' => $item['wrapping_algorithm'],
                    'format_version' => $item['format_version'],
                ]);
            }

            $locked->update([
                'current_key_version' => $keyVersion,
                'e2ee_rotation_required_at' => null,
            ]);

            return $locked->fresh();
        });
    }
}
