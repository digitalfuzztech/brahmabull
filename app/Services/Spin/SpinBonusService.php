<?php

namespace App\Services\Spin;

use App\Models\BrahmaPlayRequest;
use App\Models\Deposit;
use App\Models\Notification;
use App\Models\SpinPromotionalPointLedger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpinBonusService
{
    public const SOURCES = [Deposit::class, BrahmaPlayRequest::class];

    public function pending(User $player): int
    {
        return (int) SpinPromotionalPointLedger::where('user_id', $player->id)
            ->where('point_type', 'bonus_points')
            ->whereIn('status', ['pending', 'partially_fulfilled'])
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');
    }

    public function fulfill(User $staff, Model $source): int
    {
        $amount = $this->consume($staff, $source, true);

        $this->notifyFulfillment($staff, $source->fresh('user'), $amount);

        return $amount;
    }

    /** Consume all pending Bonus entries inside the successful verification transaction. */
    public function consumeForVerification(User $staff, Model $source): int
    {
        return $this->consume($staff, $source, false);
    }

    private function consume(User $staff, Model $source, bool $failWhenEmpty): int
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may fulfill promotional Bonus Points.');
        }
        if (! in_array($source::class, self::SOURCES, true) || $source->status !== 'verified') {
            throw ValidationException::withMessages(['bonus' => 'The underlying request must be successfully verified first.']);
        }

        return DB::transaction(function () use ($staff, $source, $failWhenEmpty) {
            User::whereKey($source->user_id)->lockForUpdate()->firstOrFail();
            $alreadyProcessed = SpinPromotionalPointLedger::where('fulfillment_source_type', $source::class)
                ->where('fulfillment_source_id', $source->getKey())->exists();
            if ($alreadyProcessed) {
                if ($failWhenEmpty) {
                    throw ValidationException::withMessages(['bonus' => 'Promotional Bonus was already fulfilled for this request.']);
                }

                return 0;
            }
            $entries = SpinPromotionalPointLedger::where('user_id', $source->user_id)
                ->where('point_type', 'bonus_points')
                ->whereIn('status', ['pending', 'partially_fulfilled'])
                ->where('remaining_amount', '>', 0)->lockForUpdate()->get();
            $amount = (int) $entries->sum('remaining_amount');
            if ($amount < 1) {
                if ($failWhenEmpty) {
                    throw ValidationException::withMessages(['bonus' => 'This player has no pending promotional Bonus Points.']);
                }

                return 0;
            }
            foreach ($entries as $entry) {
                $entry->update([
                    'remaining_amount' => 0,
                    'status' => 'fulfilled',
                    'processed_by' => $staff->id,
                    'processed_at' => now(),
                    'fulfillment_source_type' => $source::class,
                    'fulfillment_source_id' => $source->getKey(),
                ]);
            }

            return $amount;
        }, 3);
    }

    private function notifyFulfillment(User $staff, Model $source, int $amount): void
    {
        $isPlay = $source instanceof BrahmaPlayRequest;
        $requested = (float) ($isPlay ? $source->points_to_load : $source->amount);
        $total = $requested + $amount;
        $details = 'Requested '.($isPlay ? 'Points' : 'Amount').': '.number_format($requested, 2)
            ."\nBonus Points Awarded: ".number_format($amount, 2)
            ."\nTotal ".($isPlay ? 'Points Loaded' : 'Promotional Reference').': '.number_format($total, 2);

        Notification::firstOrCreate([
            'user_id' => $source->user_id,
            'type' => 'spin_bonus_fulfilled',
            'entity_type' => $source::class,
            'entity_id' => $source->getKey(),
        ], [
            'title' => $isPlay ? 'Brahma Play request verified' : 'Promotional Bonus Awarded',
            'message' => ($isPlay ? 'Your Brahma Play request has been verified.' : 'Your promotional Bonus has been awarded with your verified deposit.')."\n\n".$details,
            'action_text' => 'View Spin Wins',
            'action_url' => route('profile'),
            'is_read' => false,
            'created_by' => $staff->id,
        ]);

        foreach (User::role(['admin', 'agent'])->where('is_active', true)->get() as $receiver) {
            Notification::firstOrCreate([
                'user_id' => $receiver->id,
                'type' => 'spin_bonus_fulfilled_staff',
                'entity_type' => $source::class,
                'entity_id' => $source->getKey(),
            ], [
                'title' => $isPlay ? 'Brahma Play Bonus Awarded' : 'Deposit Bonus Awarded',
                'message' => 'Player: '.$source->user->name."\n".$details,
                'action_text' => $isPlay ? 'View Brahma Plays' : 'View Deposits',
                'action_url' => $isPlay
                    ? route($receiver->hasRole('admin') ? 'admin.brahma.plays' : 'agent.brahma.plays')
                    : route($receiver->hasRole('admin') ? 'admin.deposits' : 'agent.deposits'),
                'is_read' => false,
                'created_by' => $staff->id,
            ]);
        }
    }
}
