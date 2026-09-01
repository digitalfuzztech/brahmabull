<?php

namespace App\Services\Chat;

use Illuminate\Support\Str;

class DefaultChatbotIntentService
{
    private const ALIASES = [
        'play' => [
            "my request isn't verified yet",
            'my request isn’t verified yet',
            'my play is pending',
            'play request pending',
            'waiting for game',
            'game request not verified',
            "my game isn't loaded",
            'my game is not loaded',
        ],
        'deposit' => [
            'my deposit is pending',
            'deposit not verified',
            "deposit isn't verified",
            'deposit isn’t verified',
            'waiting for deposit',
        ],
        'cashout' => [
            'cashout pending',
            "my cashout isn't approved",
            'my cashout isn’t approved',
            'withdrawal pending',
            'cashout not verified',
        ],
        'balance' => [
            'brahma balance',
            'what is my balance',
            'show my balance',
        ],
        'support' => [
            'talk to support',
            'talk to someone',
            'i need help',
            'contact support',
        ],
        'menu' => ['main menu', 'show menu'],
        'other' => ['other'],
    ];

    public function match(string $input): ?string
    {
        $normalized = $this->normalize($input);

        foreach (self::ALIASES as $intent => $aliases) {
            if (collect($aliases)->map($this->normalize(...))->contains($normalized)) {
                return $intent;
            }
        }

        foreach (self::ALIASES as $intent => $aliases) {
            if (in_array($intent, ['menu', 'other'], true)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (Str::contains($normalized, $this->normalize($alias))) {
                    return $intent;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->replace('’', "'")
            ->lower()
            ->squish()
            ->toString();
    }
}
