<?php

namespace App\Services\Spin;

use App\Models\SpinWheelSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SpinCategoryProbabilityService
{
    public const DEFAULTS = [
        'try_again' => 50,
        'free_spin' => 20,
        'bonus_points' => 10,
        'sajilo_points' => 10,
        'badge' => 10,
    ];

    public function configured(SpinWheelSetting $settings): array
    {
        return collect(self::DEFAULTS)->mapWithKeys(fn ($default, $category) => [
            $category => (int) ($settings->{$category.'_chance'} ?? $default),
        ])->all();
    }

    public function validate(array $weights): void
    {
        if (array_keys($weights) !== array_keys(self::DEFAULTS)
            || collect($weights)->contains(fn ($weight) => ! is_int($weight) || $weight < 0)
            || array_sum($weights) !== 100
            || ! collect($weights)->contains(fn ($weight) => $weight > 0)) {
            throw ValidationException::withMessages(['settingForm.reward_chances' => 'Reward chances must be whole numbers of zero or more and total exactly 100%.']);
        }
    }

    public function cumulativeRanges(array $weights): array
    {
        $this->validate($weights);
        $cursor = 0;

        return collect($weights)->mapWithKeys(function (int $weight, string $category) use (&$cursor) {
            $start = $cursor + 1;
            $cursor += $weight;

            return [$category => ['from' => $weight ? $start : null, 'to' => $weight ? $cursor : null, 'weight' => $weight]];
        })->all();
    }

    public function select(SpinWheelSetting $settings, Collection $eligibleCategories): string
    {
        $configured = $this->configured($settings);
        $this->validate($configured);
        $category = $this->categoryForTicket($configured, random_int(1, 100));

        if (! $eligibleCategories->contains($category)) {
            throw ValidationException::withMessages([
                'spin' => str($category)->headline().' is configured with a reward chance but has no active eligible offer/slot.',
            ]);
        }

        return $category;
    }

    public function missingEligibleCategories(SpinWheelSetting $settings, Collection $eligibleCategories): Collection
    {
        return collect($this->configured($settings))
            ->filter(fn (int $weight) => $weight > 0)
            ->keys()
            ->diff($eligibleCategories)
            ->values();
    }

    public function categoryForTicket(array $weights, int $ticket): string
    {
        $ranges = $this->cumulativeRanges($weights);
        if ($ticket < 1 || $ticket > 100) {
            throw ValidationException::withMessages(['spin' => 'The reward draw ticket must be between 1 and 100.']);
        }

        foreach ($ranges as $category => $range) {
            if ($range['from'] !== null && $ticket >= $range['from'] && $ticket <= $range['to']) {
                return $category;
            }
        }

        throw ValidationException::withMessages(['spin' => 'No promotional prize category matched the reward draw.']);
    }
}
