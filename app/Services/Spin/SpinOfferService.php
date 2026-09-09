<?php

namespace App\Services\Spin;

use App\Models\SpinWheelAssignment;
use App\Models\SpinWheelOffer;
use App\Models\SpinWheelOfferType;
use App\Models\SpinWheelSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpinOfferService
{
    public const ACTIONS = ['try_again', 'free_spin', 'bonus_points', 'sajilo_points', 'badge'];

    public const ACTION_LABELS = [
        'try_again' => 'Try Again',
        'free_spin' => 'Free Spin',
        'bonus_points' => 'Bonus Points',
        'sajilo_points' => 'Sajilo Points',
        'badge' => 'Badge',
    ];

    public const CATEGORIES = self::ACTIONS;

    public function assertAdmin(User $user): void
    {
        if (! $user->hasRole('admin')) {
            throw new AuthorizationException('Only Administrators may manage the Spin Wheel.');
        }
    }

    public function saveType(User $admin, ?SpinWheelOfferType $type, array $data): SpinWheelOfferType
    {
        $this->assertAdmin($admin);
        if (! in_array($data['action_type'], self::ACTIONS, true)) {
            throw ValidationException::withMessages(['typeAction' => 'Unsupported promotional action.']);
        }

        return DB::transaction(function () use ($admin, $type, $data) {
            SpinWheelSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            if (($data['is_active'] ?? true) && SpinWheelOfferType::where('is_active', true)
                ->when($type, fn ($query) => $query->whereKeyNot($type->id))->count() >= 10) {
                throw ValidationException::withMessages(['typeActive' => 'Only ten Offer Types may be active.']);
            }
            $type ??= new SpinWheelOfferType(['created_by' => $admin->id]);
            $baseSlug = Str::slug($data['name']) ?: 'offer-type';
            $slug = $baseSlug;
            $suffix = 2;
            while (SpinWheelOfferType::where('slug', $slug)->when($type->exists, fn ($query) => $query->whereKeyNot($type->id))->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }
            unset($data['slug'], $data['description']);
            $type->fill($data + ['slug' => $slug, 'description' => null, 'updated_by' => $admin->id])->save();

            return $type;
        });
    }

    public function saveOffer(User $admin, ?SpinWheelOffer $offer, array $data): SpinWheelOffer
    {
        $this->assertAdmin($admin);
        $type = SpinWheelOfferType::findOrFail($data['offer_type_id']);
        if (! $type->is_active && (! $offer || $offer->offer_type_id !== $type->id)) {
            throw ValidationException::withMessages(['offerTypeId' => 'Choose an active offer type.']);
        }
        $value = $data['display_value'] ?? null;
        if ($type->action_type === 'free_spin' && (! ctype_digit((string) $value) || (int) $value < 1 || (int) $value > 3)) {
            throw ValidationException::withMessages(['offerDisplayValue' => 'Free Spin value must be 1, 2, or 3.']);
        }
        if (in_array($type->action_type, ['bonus_points', 'sajilo_points'], true)
            && (! ctype_digit((string) $value) || (int) $value < 1)) {
            throw ValidationException::withMessages(['offerDisplayValue' => $this->actionLabel($type->action_type).' value must be a positive whole number.']);
        }
        if (in_array($type->action_type, ['try_again', 'badge'], true)) {
            $data['display_value'] = null;
        }
        $offer ??= new SpinWheelOffer(['created_by' => $admin->id]);
        $offer->fill($data + ['updated_by' => $admin->id])->save();

        return $offer;
    }

    private function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? 'Reward';
    }

    public function saveAssignment(User $admin, ?SpinWheelAssignment $assignment, array $data): SpinWheelAssignment
    {
        $this->assertAdmin($admin);
        if (! in_array($data['slot_type'], ['numeric', 'featured'], true)) {
            throw ValidationException::withMessages(['assignmentSlotType' => 'Choose a valid wheel slot.']);
        }
        $maximum = $data['slot_type'] === 'numeric' ? 10 : 6;
        if ($data['slot_position'] < 1 || $data['slot_position'] > $maximum) {
            throw ValidationException::withMessages(['assignmentSlotPosition' => 'Choose a valid wheel slot.']);
        }
        $conflict = SpinWheelAssignment::where('slot_type', $data['slot_type'])->where('slot_position', $data['slot_position'])
            ->when($assignment, fn ($query) => $query->whereKeyNot($assignment->id))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['assignmentSlotPosition' => 'That wheel slot is already assigned.']);
        }
        SpinWheelOffer::findOrFail($data['offer_id']);
        $assignment ??= new SpinWheelAssignment;
        $data['wheel_number'] = 100 + $this->visualSlot($data['slot_type'], $data['slot_position']);
        $data['is_featured'] = $data['slot_type'] === 'featured';
        $assignment->fill($data)->save();

        return $assignment;
    }

    public function deleteType(User $admin, SpinWheelOfferType $type): void
    {
        $this->assertAdmin($admin);
        if ($type->offers()->exists()) {
            $type->update(['is_active' => false, 'updated_by' => $admin->id]);

            return;
        }
        $type->delete();
    }

    public function deleteAssignment(User $admin, SpinWheelAssignment $assignment): void
    {
        $this->assertAdmin($admin);
        $assignment->delete();
    }

    public function deleteOffer(User $admin, SpinWheelOffer $offer): void
    {
        $this->assertAdmin($admin);
        if ($offer->spins()->exists()) {
            $offer->update(['is_active' => false]);

            return;
        }
        $offer->assignments()->delete();
        $offer->delete();
    }

    public function visualSlot(string $type, int $position): int
    {
        $order = [
            ['numeric', 1], ['numeric', 5], ['numeric', 6], ['numeric', 3],
            ['featured', 1], ['numeric', 2], ['numeric', 4], ['numeric', 9],
            ['numeric', 10], ['featured', 2], ['numeric', 8], ['featured', 3],
            ['numeric', 7], ['featured', 4], ['featured', 5], ['featured', 6],
        ];
        foreach ($order as $index => [$slotType, $slotPosition]) {
            if ($type === $slotType && $position === $slotPosition) {
                return $index + 1;
            }
        }

        throw ValidationException::withMessages(['assignmentSlotPosition' => 'Choose a valid wheel slot.']);
    }

    public function categoryWarnings(SpinWheelSetting $settings): array
    {
        return $this->categoryWarningsForWeights(collect(SpinCategoryProbabilityService::DEFAULTS)->mapWithKeys(fn ($default, $category) => [
            $category => (int) ($settings->{$category.'_chance'} ?? $default),
        ])->all());
    }

    public function categoryWarningsForWeights(array $weights): array
    {
        $now = now();
        $eligible = SpinWheelAssignment::query()->where('is_active', true)
            ->whereIn('slot_type', ['numeric', 'featured'])
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->whereHas('offer', fn ($query) => $query->where('is_active', true)
                ->where(fn ($offer) => $offer->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($offer) => $offer->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->whereHas('type', fn ($type) => $type->where('is_active', true)))
            ->with('offer.type')->get()->pluck('offer.type.action_type')->unique();

        return collect($weights)
            ->filter(fn ($weight, $category) => (int) $weight > 0 && ! $eligible->contains($category))
            ->map(fn ($weight, $category) => (self::ACTION_LABELS[$category] ?? Str::headline($category))
                .' category is configured for '.(int) $weight.'% but has no active eligible offer/slot.')
            ->values()->all();
    }
}
