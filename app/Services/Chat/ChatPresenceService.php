<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

class ChatPresenceService
{
    public const TTL_SECONDS = 75;

    public function heartbeat(User $staff): void
    {
        $this->assertStaff($staff);
        Cache::put($this->key($staff), now()->toISOString(), now()->addSeconds(self::TTL_SECONDS));
    }

    public function isOnline(User $staff): bool
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            return false;
        }

        return Cache::has($this->key($staff));
    }

    private function key(User $staff): string
    {
        return 'chat-presence:user:'.$staff->id;
    }

    private function assertStaff(User $staff): void
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Presence is available only to staff.');
        }
    }
}
