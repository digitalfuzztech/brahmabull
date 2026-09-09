<?php

namespace App\Services\Spin;

use App\Models\SpinWheelSpin;
use Illuminate\Database\Eloquent\Builder;

class SpinStatisticsService
{
    public const WIN_TYPES = ['sajilo_points', 'bonus_points', 'badge'];

    public function topWins(): Builder
    {
        return SpinWheelSpin::with('user')->whereIn('offer_snapshot_type', self::WIN_TYPES)
            ->orderByDesc('offer_snapshot_score')->orderByDesc('spun_at');
    }

    public function winners(?int $month = null, ?int $year = null)
    {
        return SpinWheelSpin::query()->whereIn('offer_snapshot_type', self::WIN_TYPES)
            ->selectRaw('user_id, COUNT(*) total_spins, COUNT(*) total_wins, SUM(offer_snapshot_score) reward_score, MAX(offer_snapshot_score) best_score, MAX(spun_at) last_win')
            ->when($month, fn ($q) => $q->whereMonth('spun_at', $month))->when($year, fn ($q) => $q->whereYear('spun_at', $year))
            ->groupBy('user_id')->orderByDesc('reward_score');
    }
}
