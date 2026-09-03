<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatE2eeConversationKey;
use App\Models\ChatE2eeDevice;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class E2eeDeviceTrustService
{
    private const CHALLENGE_TTL_SECONDS = 300;

    public function __construct(
        private readonly E2eeAuthorizationService $authorization,
        private readonly E2eeConversationKeyService $keys,
        private readonly E2eeDeviceProofService $proofs,
    ) {}

    public function approvalPlan(User $actor, ChatE2eeDevice $target, string $approverDeviceUuid): array
    {
        $this->authorization->assertStaff($actor);
        $this->assertTarget($actor, $target);
        $approver = $this->trustedOwnDevice($actor, $approverDeviceUuid);

        if ($approver->is($target)) {
            throw new AuthorizationException('An untrusted device cannot approve itself.');
        }

        $conversations = $this->requiredConversations($actor);
        $plans = $conversations->map(function (ChatConversation $conversation) use ($approver): array {
            $key = ChatE2eeConversationKey::query()
                ->where('conversation_id', $conversation->id)
                ->where('device_id', $approver->id)
                ->where('key_version', $conversation->current_key_version)
                ->first();

            if (! $key) {
                throw new DomainException('The approving device does not possess every required current conversation key.');
            }

            return [
                'conversation_id' => $conversation->id,
                'key_version' => (int) $conversation->current_key_version,
                'approver_wrapped_key' => $key->wrapped_key,
            ];
        })->values()->all();

        $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Cache::put($this->challengeKey($actor, $approver, $target), $challenge, self::CHALLENGE_TTL_SECONDS);

        return [
            'challenge' => $challenge,
            'approver_device' => $this->publicDevice($approver),
            'target_device' => $this->publicDevice($target),
            'conversations' => $plans,
            'historical_keys_included' => false,
        ];
    }

    public function approve(
        User $actor,
        ChatE2eeDevice $target,
        string $approverDeviceUuid,
        string $challenge,
        array $provisioning,
        string $signature,
    ): ChatE2eeDevice {
        $this->authorization->assertStaff($actor);

        return DB::transaction(function () use ($actor, $target, $approverDeviceUuid, $challenge, $provisioning, $signature): ChatE2eeDevice {
            $lockedTarget = ChatE2eeDevice::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($lockedTarget->user_id !== $actor->id) {
                throw new AuthorizationException('You may approve only your own E2EE devices.');
            }
            if ($lockedTarget->revoked_at !== null) {
                throw new AuthorizationException('A revoked E2EE device cannot be approved.');
            }
            if ($lockedTarget->trusted_at !== null) {
                return $lockedTarget;
            }

            $approver = $this->trustedOwnDevice($actor, $approverDeviceUuid, true);
            if ($approver->is($lockedTarget)) {
                throw new AuthorizationException('An untrusted device cannot approve itself.');
            }

            $expectedChallenge = Cache::get($this->challengeKey($actor, $approver, $lockedTarget));
            if (! is_string($expectedChallenge) || ! hash_equals($expectedChallenge, $challenge)) {
                throw new DomainException('The device-approval challenge is missing or expired.');
            }

            $normalized = $this->proofs->normalizedProvisioning($provisioning);
            $required = $this->requiredConversations($actor, true);
            $expectedPairs = $required->map(fn (ChatConversation $conversation) => [
                'conversation_id' => $conversation->id,
                'key_version' => (int) $conversation->current_key_version,
            ])->values()->all();
            $providedPairs = collect($normalized)->map(fn (array $item) => [
                'conversation_id' => $item['conversation_id'],
                'key_version' => $item['key_version'],
            ])->values()->all();

            if ($providedPairs !== $expectedPairs) {
                throw new DomainException('Device approval requires every current non-rotating E2EE conversation key.');
            }

            foreach ($required as $conversation) {
                if (! ChatE2eeConversationKey::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('device_id', $approver->id)
                    ->where('key_version', $conversation->current_key_version)
                    ->exists()) {
                    throw new DomainException('The approving device does not possess every required current conversation key.');
                }
            }

            $canonical = $this->proofs->approvalCanonical(
                $actor->id,
                $approver->id,
                $lockedTarget->id,
                $challenge,
                $normalized,
            );
            $this->proofs->assertSignature($approver, $canonical, $signature);

            foreach ($normalized as $item) {
                $conversation = $required->firstWhere('id', $item['conversation_id']);
                if (! $conversation) {
                    throw new DomainException('The approval contains an unauthorized conversation.');
                }
                $this->authorization->assertDirectParticipant($conversation, $actor);
                $this->keys->validateWrappedKey(
                    $item['wrapped_key'],
                    $item['wrapping_algorithm'],
                    $item['format_version'],
                );

                $existing = ChatE2eeConversationKey::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('device_id', $lockedTarget->id)
                    ->where('key_version', $item['key_version'])
                    ->lockForUpdate()
                    ->first();
                if ($existing && $existing->wrapped_key !== $item['wrapped_key']) {
                    throw new DomainException('Existing wrapped key material cannot be replaced during device approval.');
                }
                $existing ?? ChatE2eeConversationKey::create([
                    'conversation_id' => $conversation->id,
                    'device_id' => $lockedTarget->id,
                    'key_version' => $item['key_version'],
                    'wrapped_key' => $item['wrapped_key'],
                    'wrapping_algorithm' => $item['wrapping_algorithm'],
                    'format_version' => $item['format_version'],
                ]);
            }

            $lockedTarget->update(['trusted_at' => now()]);
            Cache::forget($this->challengeKey($actor, $approver, $lockedTarget));

            return $lockedTarget->fresh();
        });
    }

    private function requiredConversations(User $actor, bool $lock = false)
    {
        $query = ChatConversation::query()
            ->where('conversation_type', 'internal_direct')
            ->where('encryption_mode', 'e2ee_v1')
            ->whereNull('e2ee_rotation_required_at')
            ->whereHas('activeParticipants', fn ($participants) => $participants->where('user_id', $actor->id))
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->each(fn (ChatConversation $conversation) => $this->authorization->assertDirectParticipant($conversation, $actor));
    }

    private function trustedOwnDevice(User $actor, string $deviceUuid, bool $lock = false): ChatE2eeDevice
    {
        $query = ChatE2eeDevice::query()
            ->where('user_id', $actor->id)
            ->where('device_uuid', $deviceUuid)
            ->whereNotNull('trusted_at')
            ->whereNull('revoked_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function assertTarget(User $actor, ChatE2eeDevice $target): void
    {
        if ($target->user_id !== $actor->id) {
            throw new AuthorizationException('You may approve only your own E2EE devices.');
        }
        if ($target->trusted_at !== null || $target->revoked_at !== null) {
            throw new AuthorizationException('Only an active untrusted device may be approved.');
        }
    }

    private function challengeKey(User $actor, ChatE2eeDevice $approver, ChatE2eeDevice $target): string
    {
        return "chat-e2ee:approval:{$actor->id}:{$approver->id}:{$target->id}";
    }

    private function publicDevice(ChatE2eeDevice $device): array
    {
        return $device->only([
            'id', 'device_uuid', 'device_name', 'public_encryption_key', 'public_signing_key', 'key_fingerprint',
        ]);
    }
}
