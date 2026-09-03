<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatE2eeConversationKey;
use App\Models\ChatE2eeDevice;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class E2eeConversationKeyService
{
    public const WRAPPING_ALGORITHM = 'x25519-sealedbox';

    public const FORMAT_VERSION = 1;

    public function __construct(private readonly E2eeAuthorizationService $authorization) {}

    public function storeWrappedKey(
        ChatConversation $conversation,
        User $actor,
        ChatE2eeDevice $device,
        int $keyVersion,
        string $wrappedKey,
        string $wrappingAlgorithm = self::WRAPPING_ALGORITHM,
        int $formatVersion = self::FORMAT_VERSION,
    ): ChatE2eeConversationKey {
        $this->validateWrappedKey($wrappedKey, $wrappingAlgorithm, $formatVersion);

        return DB::transaction(function () use ($conversation, $actor, $device, $keyVersion, $wrappedKey, $wrappingAlgorithm, $formatVersion): ChatE2eeConversationKey {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $participantIds = $this->authorization->assertDirectParticipant($locked, $actor);
            $target = ChatE2eeDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();

            if (! in_array($target->user_id, $participantIds, true)) {
                throw new AuthorizationException('Wrapped keys may target only devices of the two direct participants.');
            }

            if (! $target->isTrustedAndActive()) {
                throw new AuthorizationException('Wrapped keys require an active trusted device.');
            }

            $maximumVersion = (int) ChatE2eeConversationKey::where('conversation_id', $locked->id)->max('key_version');
            $expectedFirstOrNext = $maximumVersion === 0 ? 1 : $maximumVersion + 1;

            if ($keyVersion < 1 || $keyVersion < $maximumVersion || $keyVersion > $expectedFirstOrNext) {
                throw new DomainException('The wrapped-key version is not the current or next provisioning version.');
            }

            $existing = ChatE2eeConversationKey::query()
                ->where('conversation_id', $locked->id)
                ->where('device_id', $target->id)
                ->where('key_version', $keyVersion)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->wrapped_key !== $wrappedKey
                    || $existing->wrapping_algorithm !== $wrappingAlgorithm
                    || $existing->format_version !== $formatVersion) {
                    throw new DomainException('Wrapped key material is immutable for a device and key version.');
                }

                return $existing;
            }

            return ChatE2eeConversationKey::create([
                'conversation_id' => $locked->id,
                'device_id' => $target->id,
                'key_version' => $keyVersion,
                'wrapped_key' => $wrappedKey,
                'wrapping_algorithm' => $wrappingAlgorithm,
                'format_version' => $formatVersion,
            ]);
        });
    }

    public function nextProvisioningVersion(ChatConversation $conversation, User $actor): int
    {
        $this->authorization->assertDirectParticipant($conversation, $actor);

        return ((int) $conversation->e2eeConversationKeys()->max('key_version')) + 1;
    }

    public function wrappedKeyForOwnDevice(
        ChatConversation $conversation,
        User $actor,
        string $deviceUuid,
        ?int $keyVersion = null,
    ): array {
        $this->authorization->assertDirectParticipant($conversation, $actor);

        $hasAccessibleHistory = $conversation->encryption_mode === 'e2ee_v1'
            || ($conversation->encryption_mode === null && $conversation->e2ee_disabled_at !== null);
        if (! $hasAccessibleHistory || ! $conversation->current_key_version) {
            throw new AuthorizationException('This direct conversation has no accessible E2EE history.');
        }

        $device = ChatE2eeDevice::query()
            ->where('user_id', $actor->id)
            ->where('device_uuid', $deviceUuid)
            ->whereNotNull('trusted_at')
            ->whereNull('revoked_at')
            ->firstOrFail();
        $requestedVersion = $keyVersion ?? (int) $conversation->current_key_version;
        if ($requestedVersion < 1 || $requestedVersion > (int) $conversation->current_key_version) {
            throw new AuthorizationException('The requested conversation-key version is unavailable.');
        }

        $key = ChatE2eeConversationKey::query()
            ->where('conversation_id', $conversation->id)
            ->where('device_id', $device->id)
            ->where('key_version', $requestedVersion)
            ->first();

        if (! $key) {
            throw new AuthorizationException('This device has not been approved to decrypt this conversation.');
        }

        return [
            'device_id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'key_version' => $key->key_version,
            'wrapped_key' => $key->wrapped_key,
            'wrapping_algorithm' => $key->wrapping_algorithm,
            'format_version' => $key->format_version,
        ];
    }

    public function validateWrappedKey(
        string $wrappedKey,
        string $wrappingAlgorithm,
        int $formatVersion,
    ): void {
        if ($wrappingAlgorithm !== self::WRAPPING_ALGORITHM || $formatVersion !== self::FORMAT_VERSION) {
            throw new DomainException('Unsupported E2EE wrapped-key format.');
        }

        $decoded = $this->decodeBase64Url($wrappedKey);
        if ($decoded === false || strlen($decoded) < 48 || strlen($decoded) > 4096) {
            throw new DomainException('The wrapped conversation key is malformed.');
        }
    }

    private function decodeBase64Url(string $value): string|false
    {
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return false;
        }

        $padding = (4 - strlen($value) % 4) % 4;

        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);
    }
}
