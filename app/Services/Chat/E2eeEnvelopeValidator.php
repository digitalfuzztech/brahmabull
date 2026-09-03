<?php

namespace App\Services\Chat;

use DomainException;
use JsonException;

class E2eeEnvelopeValidator
{
    public const ALGORITHM = 'xchacha20poly1305-ietf';

    public const FORMAT_VERSION = 1;

    public const MAX_ENVELOPE_BYTES = 262144;

    public function validate(string $encodedEnvelope): array
    {
        if (strlen($encodedEnvelope) > self::MAX_ENVELOPE_BYTES) {
            throw new DomainException('The encrypted envelope is too large.');
        }

        try {
            $envelope = json_decode($encodedEnvelope, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('The encrypted envelope is not valid JSON.');
        }

        $actualKeys = is_array($envelope) ? array_keys($envelope) : [];
        $expectedKeys = ['v', 'alg', 'key_version', 'nonce', 'ciphertext'];
        sort($actualKeys);
        sort($expectedKeys);

        if (! is_array($envelope)
            || $actualKeys !== $expectedKeys
            || $envelope['v'] !== self::FORMAT_VERSION
            || $envelope['alg'] !== self::ALGORITHM
            || ! is_int($envelope['key_version'])
            || $envelope['key_version'] < 1
            || ! is_string($envelope['nonce'])
            || ! is_string($envelope['ciphertext'])) {
            throw new DomainException('The encrypted envelope format is invalid or unsupported.');
        }

        $nonce = $this->decodeBase64Url($envelope['nonce']);
        $ciphertext = $this->decodeBase64Url($envelope['ciphertext']);

        if ($nonce === false || strlen($nonce) !== 24 || $ciphertext === false || strlen($ciphertext) < 16) {
            throw new DomainException('The encrypted envelope encoding is malformed.');
        }

        return $envelope;
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
