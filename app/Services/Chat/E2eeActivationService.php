<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatE2eeConversationKey;
use App\Models\ChatE2eeDevice;
use App\Models\ChatMessage;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class E2eeActivationService
{
    public function __construct(
        private readonly E2eeAuthorizationService $authorization,
        private readonly E2eeConversationKeyService $keys,
    ) {}

    public function activate(ChatConversation $conversation, User $actor, int $keyVersion, array $wrappedKeys): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $actor, $keyVersion, $wrappedKeys): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $participantIds = $this->authorization->assertDirectParticipant($locked, $actor);

            if ($locked->encryption_mode === 'e2ee_v1') {
                return $locked;
            }

            $isFirstActivation = $locked->encryption_mode === null
                && $locked->e2ee_enabled_at === null
                && $locked->current_key_version === null
                && $locked->e2ee_disabled_at === null;
            $isReactivation = $locked->encryption_mode === null
                && $locked->e2ee_enabled_at === null
                && $locked->current_key_version !== null
                && $locked->e2ee_disabled_at !== null;

            if (! $isFirstActivation && ! $isReactivation) {
                throw new DomainException('This direct conversation has an unsupported encryption state.');
            }

            $expectedVersion = $isFirstActivation ? 1 : ((int) $locked->current_key_version + 1);
            if ($keyVersion !== $expectedVersion) {
                throw new DomainException('Activation must use the next fresh conversation-key version.');
            }

            $expectedDevices = ChatE2eeDevice::query()
                ->whereIn('user_id', $participantIds)
                ->whereNotNull('trusted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            foreach ($participantIds as $participantId) {
                if (! $expectedDevices->contains('user_id', $participantId)) {
                    throw new DomainException('Both participants need a trusted secure device before E2EE can be enabled.');
                }
            }

            $provided = collect($wrappedKeys)->keyBy(fn (array $item) => (int) ($item['device_id'] ?? 0));
            $expectedIds = $expectedDevices->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $providedIds = $provided->keys()->map(fn ($id) => (int) $id)->sort()->values();

            if ($provided->count() !== count($wrappedKeys) || $providedIds->all() !== $expectedIds->all()) {
                throw new DomainException('Activation requires exactly one wrapped key for every trusted participant device.');
            }

            foreach ($expectedDevices as $device) {
                $item = $provided->get($device->id);
                $this->keys->storeWrappedKey(
                    $locked,
                    $actor,
                    $device,
                    $keyVersion,
                    (string) ($item['wrapped_key'] ?? ''),
                    (string) ($item['wrapping_algorithm'] ?? ''),
                    (int) ($item['format_version'] ?? 0),
                );
            }

            $provisionedIds = ChatE2eeConversationKey::query()
                ->where('conversation_id', $locked->id)
                ->where('key_version', $keyVersion)
                ->pluck('device_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            if ($provisionedIds->all() !== $expectedIds->all()) {
                throw new DomainException('The complete activation device key set was not provisioned.');
            }

            $enabledAt = now();
            $locked->update([
                'encryption_mode' => 'e2ee_v1',
                'e2ee_enabled_at' => $enabledAt,
                'current_key_version' => $keyVersion,
                'e2ee_disable_requested_by' => null,
                'e2ee_disable_requested_at' => null,
                'last_message_at' => $enabledAt,
            ]);

            ChatMessage::create([
                'conversation_id' => $locked->id,
                'sender_type' => 'system',
                'message_type' => 'system',
                'body' => 'End-to-end encryption was enabled for new messages.',
                'metadata' => ['e2ee_boundary' => true],
                'read_by_staff_at' => $enabledAt,
            ]);

            return $locked->fresh();
        });
    }
}
