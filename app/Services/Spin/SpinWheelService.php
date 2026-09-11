<?php

namespace App\Services\Spin;

use App\Models\SpinAttemptGrant;
use App\Models\SpinRewardEntitlement;
use App\Models\SpinWheelAssignment;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpin;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SpinWheelService
{
    public function __construct(
        private readonly SpinRewardService $rewards,
        private readonly SpinCategoryProbabilityService $probabilities,
    ) {}

    public function spin(User $player, string $requestToken): SpinWheelSpin
    {
        if (! $player->hasRole('player')) {
            throw new AuthorizationException('Only players may spin.');
        }
        if (! filter_var($requestToken, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[0-9a-f-]{36}$/i']])) {
            throw ValidationException::withMessages(['spin' => 'Invalid spin request.']);
        }

        return DB::transaction(function () use ($player, $requestToken) {
            if ($existing = SpinWheelSpin::where('request_token', $requestToken)->where('user_id', $player->id)->first()) {
                return $existing;
            }
            $player = User::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            $settings = SpinWheelSetting::find(1);
            if (! $settings?->is_enabled) {
                throw ValidationException::withMessages(['spin' => 'The Spin Wheel is currently unavailable.']);
            }
            $grant = SpinAttemptGrant::where('user_id', $player->id)->where('attempts_remaining', '>', 0)
                ->orderBy('granted_at')->lockForUpdate()->first();
            if (! $grant) {
                throw ValidationException::withMessages(['spin' => 'You do not have an available spin.']);
            }
            if ($existing = SpinWheelSpin::where('request_token', $requestToken)->where('user_id', $player->id)->first()) {
                return $existing;
            }
            $now = now();
            $assignments = SpinWheelAssignment::query()->with('offer.type')->where('is_active', true)
                ->whereIn('slot_type', ['numeric', 'featured'])
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->whereHas('offer', fn ($q) => $q->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                    ->whereHas('type', fn ($type) => $type->where('is_active', true)))
                ->get();
            if ($assignments->isEmpty()) {
                Log::warning('Spin Wheel has no eligible promotional landing slots.', ['user_id' => $player->id]);
                throw ValidationException::withMessages(['spin' => 'The Spin Wheel is temporarily unavailable. Your spin was not used.']);
            }
            $eligibleCategories = $assignments->pluck('offer.type.action_type')->unique()->values();
            $missingCategories = $this->probabilities->missingEligibleCategories($settings, $eligibleCategories);
            if ($missingCategories->isNotEmpty()) {
                Log::warning('Spin Wheel configuration has positive-weight categories without eligible landing slots.', [
                    'categories' => $missingCategories->all(),
                    'user_id' => $player->id,
                ]);
                throw ValidationException::withMessages(['spin' => 'The Spin Wheel is temporarily unavailable. Your spin was not used.']);
            }
            $category = $this->probabilities->select($settings, $eligibleCategories);
            $activeBadge = null;
            if ($category === 'badge') {
                $activeBadge = SpinRewardEntitlement::query()
                    ->where('user_id', $player->id)
                    ->where('entitlement_type', 'badge')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                    ->latest('expires_at')
                    ->lockForUpdate()
                    ->first();

                if ($activeBadge) {
                    $category = 'try_again';
                }
            }
            $categoryAssignments = $assignments->filter(fn ($item) => $item->offer->type->action_type === $category);
            if ($categoryAssignments->isEmpty()) {
                Log::warning('Spin Wheel cannot map an active VIP outcome to an eligible Try Again landing slot.', [
                    'user_id' => $player->id,
                ]);
                throw ValidationException::withMessages(['spin' => 'The Spin Wheel is temporarily unavailable. Your spin was not used.']);
            }
            $offers = $categoryAssignments->pluck('offer')->unique('id')->values();
            $offerTicket = random_int(1, (int) $offers->sum(fn ($item) => max(1, (int) $item->rarity_weight)));
            $offer = $offers->first(function ($item) use (&$offerTicket) {
                $offerTicket -= max(1, (int) $item->rarity_weight);

                return $offerTicket <= 0;
            });
            $landingSlots = $categoryAssignments->where('offer_id', $offer->id)->values();
            $assignment = $landingSlots->get(random_int(0, $landingSlots->count() - 1));
            $snapshotMetadata = ($offer->metadata ?? []) + [
                'description' => $offer->description,
                'display_label' => $assignment->display_label ?: $offer->name,
            ];
            if ($activeBadge) {
                $expiration = $activeBadge->expires_at;
                $snapshotMetadata['description'] = $expiration
                    ? 'You already have a VIP badge valid until '.$expiration->format('F j, Y g:i A').'.'
                    : 'You already have an active VIP badge.';
                $snapshotMetadata['display_label'] = 'Try Again';
            }
            $spin = SpinWheelSpin::create([
                'request_token' => $requestToken, 'user_id' => $player->id, 'attempt_grant_id' => $grant->id,
                'spin_number' => ((int) SpinWheelSpin::where('user_id', $player->id)->max('spin_number')) + 1,
                'wheel_number' => $assignment->wheel_number, 'wheel_slot' => app(SpinOfferService::class)->visualSlot($assignment->slot_type, $assignment->slot_position), 'offer_id' => $offer->id,
                'offer_snapshot_name' => $activeBadge ? 'Try Again' : $offer->name,
                'offer_snapshot_value' => $activeBadge ? null : $offer->display_value,
                'offer_snapshot_type' => $activeBadge ? 'try_again' : $offer->type->action_type,
                'offer_snapshot_category' => $activeBadge ? 'try_again' : $offer->category,
                'offer_snapshot_score' => $activeBadge ? 0 : $offer->ranking_score,
                'offer_snapshot_metadata' => $snapshotMetadata,
                'status' => 'awarded', 'spun_at' => now(),
            ]);
            $grant->decrement('attempts_remaining');
            $this->rewards->award($spin, $offer);

            return $spin->fresh(['offer.type', 'claim', 'entitlement']);
        }, 3);
    }
}
