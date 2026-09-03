<?php

namespace App\Services\Chat;

use App\Models\ChatE2eeDevice;
use DomainException;

class E2eeDeviceProofService
{
    public function approvalCanonical(
        int $userId,
        int $approverDeviceId,
        int $targetDeviceId,
        string $challenge,
        array $provisioning,
    ): string {
        return $this->canonical([
            'v' => 1,
            'purpose' => 'device_approval',
            'user_id' => $userId,
            'approver_device_id' => $approverDeviceId,
            'target_device_id' => $targetDeviceId,
            'challenge' => $challenge,
            'provisioning' => $this->normalizedProvisioning($provisioning),
        ]);
    }

    public function rotationCanonical(
        int $userId,
        int $deviceId,
        int $conversationId,
        int $keyVersion,
        array $wrappedKeys,
    ): string {
        return $this->canonical([
            'v' => 1,
            'purpose' => 'conversation_rotation',
            'user_id' => $userId,
            'device_id' => $deviceId,
            'conversation_id' => $conversationId,
            'key_version' => $keyVersion,
            'wrapped_keys' => $this->normalizedWrappedKeys($wrappedKeys),
        ]);
    }

    public function assertSignature(ChatE2eeDevice $device, string $canonical, string $signature): void
    {
        $publicKey = $this->decodeBase64Url($device->public_signing_key);
        $decodedSignature = $this->decodeBase64Url($signature);

        if ($publicKey === false
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || $decodedSignature === false
            || strlen($decodedSignature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($decodedSignature, $canonical, $publicKey)) {
            throw new DomainException('The trusted-device cryptographic approval proof is invalid.');
        }
    }

    public function normalizedProvisioning(array $provisioning): array
    {
        return collect($provisioning)
            ->map(fn (array $item) => [
                'conversation_id' => (int) ($item['conversation_id'] ?? 0),
                'key_version' => (int) ($item['key_version'] ?? 0),
                'wrapped_key' => (string) ($item['wrapped_key'] ?? ''),
                'wrapping_algorithm' => (string) ($item['wrapping_algorithm'] ?? ''),
                'format_version' => (int) ($item['format_version'] ?? 0),
            ])
            ->sort(fn (array $left, array $right) => [$left['conversation_id'], $left['key_version']] <=> [$right['conversation_id'], $right['key_version']])
            ->values()
            ->all();
    }

    public function normalizedWrappedKeys(array $wrappedKeys): array
    {
        return collect($wrappedKeys)
            ->map(fn (array $item) => [
                'device_id' => (int) ($item['device_id'] ?? 0),
                'wrapped_key' => (string) ($item['wrapped_key'] ?? ''),
                'wrapping_algorithm' => (string) ($item['wrapping_algorithm'] ?? ''),
                'format_version' => (int) ($item['format_version'] ?? 0),
            ])
            ->sortBy('device_id')
            ->values()
            ->all();
    }

    private function canonical(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
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
