<?php

namespace App\Observers;

use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Deposit;
use App\Services\Spin\SpinEligibilityService;

class PromotionalSpinEligibilityObserver
{
    public function saved(Deposit|BrahmaDeposit|BrahmaPlayRequest $event): void
    {
        if ($event->status !== 'verified' || (! $event->wasRecentlyCreated && ! $event->wasChanged('status'))) {
            return;
        }
        $player = $event->user;
        if ($player) {
            app(SpinEligibilityService::class)->grantForVerifiedEvent($event, $player);
        }
    }
}
