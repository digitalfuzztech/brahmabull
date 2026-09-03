<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatE2eeDevice;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class E2eeDeviceService
{
    public function __construct(private readonly E2eeAuthorizationService $authorization) {}

    public function register(User $actor, array $attributes): ChatE2eeDevice
    {
        $this->authorization->assertStaff($actor);
        $data = Validator::make($attributes, [
            'device_uuid' => ['required', 'string', 'min:22', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'public_encryption_key' => ['required', 'string', 'max:128'],
            'public_signing_key' => ['required', 'string', 'max:128'],
            'key_fingerprint' => ['required', 'string', 'max:128'],
            'user_id' => ['prohibited'],
            'private_key' => ['prohibited'],
            'private_encryption_key' => ['prohibited'],
            'private_signing_key' => ['prohibited'],
            'private_seed' => ['prohibited'],
            'secret_key' => ['prohibited'],
            'seed' => ['prohibited'],
            'recovery_password' => ['prohibited'],
            'conversation_key' => ['prohibited'],
        ])->validate();

        $this->assertEncodedLength($data['public_encryption_key'], 32, 'public encryption key');
        $this->assertEncodedLength($data['public_signing_key'], 32, 'public signing key');
        $this->assertEncodedLength($data['key_fingerprint'], 32, 'key fingerprint');

        return DB::transaction(function () use ($actor, $data): ChatE2eeDevice {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = ChatE2eeDevice::query()
                ->where('user_id', $actor->id)
                ->where('device_uuid', $data['device_uuid'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->public_encryption_key !== $data['public_encryption_key']
                    || $existing->public_signing_key !== $data['public_signing_key']
                    || $existing->key_fingerprint !== $data['key_fingerprint']) {
                    throw new DomainException('An existing device UUID cannot be rebound to different key material.');
                }

                $existing->update([
                    'device_name' => $data['device_name'] ?? $existing->device_name,
                    'last_used_at' => now(),
                ]);

                return $existing->fresh();
            }

            $isFirstDevice = ! ChatE2eeDevice::where('user_id', $actor->id)->exists();

            return ChatE2eeDevice::create([
                'user_id' => $actor->id,
                'device_uuid' => $data['device_uuid'],
                'device_name' => $data['device_name'] ?? null,
                'public_encryption_key' => $data['public_encryption_key'],
                'public_signing_key' => $data['public_signing_key'],
                'key_fingerprint' => $data['key_fingerprint'],
                'trusted_at' => $isFirstDevice ? now() : null,
                'last_used_at' => now(),
            ]);
        });
    }

    public function revoke(User $actor, ChatE2eeDevice $device): ChatE2eeDevice
    {
        $this->authorization->assertStaff($actor);

        if ($device->user_id !== $actor->id) {
            throw new AuthorizationException('You may revoke only your own E2EE device.');
        }

        return DB::transaction(function () use ($actor, $device): ChatE2eeDevice {
            $locked = ChatE2eeDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($locked->user_id !== $actor->id) {
                throw new AuthorizationException('You may revoke only your own E2EE device.');
            }

            if ($locked->revoked_at !== null) {
                return $locked;
            }

            $wasTrusted = $locked->trusted_at !== null;
            $locked->update(['revoked_at' => now()]);

            if ($wasTrusted) {
                ChatConversation::query()
                    ->where('conversation_type', 'internal_direct')
                    ->where('encryption_mode', 'e2ee_v1')
                    ->whereHas('e2eeConversationKeys', fn ($keys) => $keys->where('device_id', $locked->id))
                    ->lockForUpdate()
                    ->get()
                    ->each(function (ChatConversation $conversation) use ($locked): void {
                        $possessedCurrentKey = $conversation->e2eeConversationKeys()
                            ->where('device_id', $locked->id)
                            ->where('key_version', $conversation->current_key_version)
                            ->exists();

                        if ($possessedCurrentKey && $conversation->e2ee_rotation_required_at === null) {
                            $conversation->update(['e2ee_rotation_required_at' => now()]);
                        }
                    });
            }

            return $locked->fresh();
        });
    }

    public function ownDevices(User $actor): Collection
    {
        $this->authorization->assertStaff($actor);

        return ChatE2eeDevice::query()
            ->where('user_id', $actor->id)
            ->orderByRaw('revoked_at IS NOT NULL')
            ->orderByDesc('trusted_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function publicDevicesForConversation(ChatConversation $conversation, User $actor): Collection
    {
        $participantIds = $this->authorization->assertDirectParticipant($conversation, $actor);

        return ChatE2eeDevice::query()
            ->whereIn('user_id', $participantIds)
            ->whereNotNull('trusted_at')
            ->whereNull('revoked_at')
            ->orderBy('user_id')
            ->orderBy('id')
            ->get()
            ->map(fn (ChatE2eeDevice $device) => [
                'id' => $device->id,
                'user_id' => $device->user_id,
                'device_uuid' => $device->device_uuid,
                'device_name' => $device->device_name,
                'public_encryption_key' => $device->public_encryption_key,
                'public_signing_key' => $device->public_signing_key,
                'key_fingerprint' => $device->key_fingerprint,
                'trusted_at' => $device->trusted_at?->toISOString(),
            ]);
    }

    private function assertEncodedLength(string $value, int $bytes, string $label): void
    {
        $decoded = $this->decodeBase64Url($value);

        if ($decoded === false || strlen($decoded) !== $bytes) {
            throw new DomainException("The {$label} must be valid Base64URL-encoded {$bytes}-byte material.");
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
