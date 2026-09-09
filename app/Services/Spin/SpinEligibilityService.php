<?php

namespace App\Services\Spin;

use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Deposit;
use App\Models\SpinAttemptGrant;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpinEligibilityService
{
    public const ONBOARDING_SOURCE = 'onboarding';

    /** Verified records from these models may unlock promotional attempts. */
    public const QUALIFYING_SOURCES = [
        Deposit::class,
        BrahmaDeposit::class,
        BrahmaPlayRequest::class,
    ];

    public function grantForVerifiedEvent(Model $source, User $player): ?SpinAttemptGrant
    {
        if (! $player->hasRole('player') || ! in_array($source::class, self::QUALIFYING_SOURCES, true)) {
            return null;
        }

        return $this->storeGrant($source::class, $source->getKey(), $player, $this->maximumAttempts(), true);
    }

    public function grantFreeSpins(Model $source, User $player, int $attempts): ?SpinAttemptGrant
    {
        if (! $player->hasRole('player') || $source::class !== SpinWheelSpin::class || (int) $source->user_id !== (int) $player->id) {
            return null;
        }

        return $this->storeGrant($source::class, $source->getKey(), $player, max(0, min(3, $attempts)), false);
    }

    public function grantOnboarding(User $player): ?SpinAttemptGrant
    {
        if (! $player->hasRole('player')) {
            return null;
        }

        return $this->storeGrant(self::ONBOARDING_SOURCE, $player->getKey(), $player, 1, false);
    }

    private function storeGrant(string $sourceType, int $sourceId, User $player, int $attempts, bool $topUpToMaximum): SpinAttemptGrant
    {

        return DB::transaction(function () use ($sourceType, $sourceId, $player, $attempts, $topUpToMaximum) {
            // Serialize grants for one player so simultaneous top-ups use one current balance.
            User::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            $existing = SpinAttemptGrant::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
            if ($existing) {
                return $existing;
            }
            $available = (int) SpinAttemptGrant::where('user_id', $player->id)->lockForUpdate()->sum('attempts_remaining');
            $granted = $topUpToMaximum
                ? max(0, $this->maximumAttempts() - $available)
                : max(0, $attempts);

            return SpinAttemptGrant::create([
                'user_id' => $player->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'attempts_granted' => $granted,
                'attempts_remaining' => $granted,
                'granted_at' => now(),
            ]);
        });
    }

    public function available(User $player): int
    {
        return max(0, (int) SpinAttemptGrant::where('user_id', $player->id)->sum('attempts_remaining'));
    }

    private function maximumAttempts(): int
    {
        return max(1, min(3, (int) (SpinWheelSetting::find(1)?->maximum_stored_attempts ?? 3)));
    }
}
