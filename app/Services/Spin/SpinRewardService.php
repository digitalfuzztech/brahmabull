<?php

namespace App\Services\Spin;

use App\Models\BrahmaBalanceTransaction;
use App\Models\Notification;
use App\Models\SpinPromotionalPointLedger;
use App\Models\SpinRewardEntitlement;
use App\Models\SpinWheelOffer;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpinRewardService
{
    public function __construct(private readonly SpinEligibilityService $eligibility) {}

    public function award(SpinWheelSpin $spin, SpinWheelOffer $offer): void
    {
        $action = $offer->type->action_type;
        if (! in_array($action, SpinOfferService::ACTIONS, true)) {
            throw ValidationException::withMessages(['reward' => 'Unsupported promotional reward.']);
        }
        $metadata = ($offer->metadata ?? []) + ['label' => $offer->name];
        $value = is_numeric($offer->display_value) ? (int) $offer->display_value : (int) ($metadata['amount'] ?? 0);
        $entitlement = null;
        if (in_array($action, ['bonus_points', 'sajilo_points'], true)) {
            $this->awardPoints($spin, $action, max(0, $value));
        } elseif (in_array($action, ['free_spin', 'badge'], true)) {
            $entitlement = SpinRewardEntitlement::firstOrCreate(['spin_id' => $spin->id], [
                'user_id' => $spin->user_id,
                'entitlement_type' => $action,
                'numeric_value' => $action === 'free_spin' ? max(0, $value) : null,
                'code' => null,
                'metadata' => $metadata,
                'expires_at' => $action === 'badge' ? now()->addDays(max(1, min(365, (int) ($metadata['valid_days'] ?? 3)))) : null,
            ]);
        }
        if ($action === 'free_spin') {
            $this->eligibility->grantFreeSpins($spin, $player = User::findOrFail($spin->user_id), $value);
        } else {
            $player = User::findOrFail($spin->user_id);
        }
        $message = match ($action) {
            'try_again' => 'Try Again — better luck next spin!',
            'free_spin' => 'You won +'.max(0, $value).' Free '.str('Spin')->plural(max(0, $value)).'!',
            'bonus_points' => 'You won '.max(0, $value).' Bonus Points!',
            'sajilo_points' => 'You won '.max(0, $value).' Sajilo from the Spin Wheel. '.max(0, $value).' points have been added to your Brahma Balance.',
            'badge' => 'You won the '.$offer->name.'!',
        };
        if (in_array($action, ['sajilo_points', 'bonus_points', 'badge'], true)) {
            Notification::firstOrCreate([
                'user_id' => $player->id, 'type' => 'spin_wheel_win', 'entity_type' => SpinWheelSpin::class, 'entity_id' => $spin->id,
            ], [
                'title' => 'Spin Wheel Win', 'message' => $message,
                'action_text' => 'View Reward', 'action_url' => route('home', ['spin' => 'open']), 'is_read' => false,
                'data' => ['float_after' => now()->addMilliseconds((int) (SpinWheelSetting::find(1)?->animation_duration_ms ?? 4800) + 1000)->toIso8601String()],
            ]);
        }
    }

    private function awardPoints(SpinWheelSpin $spin, string $pointType, int $amount): void
    {
        if ($amount < 1) {
            return;
        }
        DB::transaction(function () use ($spin, $pointType, $amount) {
            $player = User::whereKey($spin->user_id)->lockForUpdate()->firstOrFail();
            if (SpinPromotionalPointLedger::where('spin_id', $spin->id)->exists()) {
                return;
            }
            if ($pointType === 'sajilo_points' && ! BrahmaBalanceTransaction::where('source_type', SpinWheelSpin::class)
                ->where('source_id', $spin->id)->where('type', 'credit')->exists()) {
                $before = (float) $player->brahma_balance;
                $after = $before + $amount;
                $player->forceFill(['brahma_balance' => $after])->save();
                BrahmaBalanceTransaction::create([
                    'user_id' => $player->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'source_type' => SpinWheelSpin::class,
                    'source_id' => $spin->id,
                    'performed_by' => null,
                    'description' => 'Promotional Sajilo Spin Wheel reward: '.$spin->offer_snapshot_name,
                ]);
            }
            $balance = (int) SpinPromotionalPointLedger::where('user_id', $spin->user_id)
                ->where('point_type', $pointType)->sum('remaining_amount');
            SpinPromotionalPointLedger::create([
                'spin_id' => $spin->id,
                'user_id' => $spin->user_id,
                'point_type' => $pointType,
                'amount' => $amount,
                'remaining_amount' => $amount,
                'status' => $pointType === 'bonus_points' ? 'pending' : 'awarded',
                'balance_after' => $balance + $amount,
                'source_type' => SpinWheelSpin::class,
                'source_id' => $spin->id,
                'awarded_at' => now(),
            ]);
        });
    }
}
