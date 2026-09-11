<?php

namespace App\Services;

use App\Models\Cashout;
use App\Models\PlayerRankEntry;
use App\Models\PlayerRankSetting;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class PlayerRankingService
{
    public const PERIOD_LAST_7_DAYS = 'last_7_days';

    public const PERIOD_THIS_MONTH = 'this_month';

    public const PERIOD_ALL_TIME = 'all_time';

    public const PERIODS = [
        self::PERIOD_LAST_7_DAYS,
        self::PERIOD_THIS_MONTH,
        self::PERIOD_ALL_TIME,
    ];

    public function mode(): string
    {
        return PlayerRankSetting::query()->whereKey(1)->value('mode')
            ?? PlayerRankSetting::MODE_MANUAL;
    }

    public function leaderboard(string $period): array
    {
        $this->assertPeriod($period);

        return $this->mode() === PlayerRankSetting::MODE_AUTOMATIC
            ? $this->automaticLeaderboard($period)
            : $this->manualLeaderboard($period);
    }

    public function automaticLeaderboard(string $period, ?Carbon $now = null): array
    {
        $this->assertPeriod($period);
        $now ??= now();

        $rows = Cashout::query()
            ->join('users', 'users.id', '=', 'cashouts.user_id')
            ->where('cashouts.status', 'paid')
            ->whereNotNull('cashouts.paid_at')
            ->when(
                $period === self::PERIOD_LAST_7_DAYS,
                fn ($query) => $query->whereBetween('cashouts.paid_at', [$now->copy()->subDays(7), $now]),
            )
            ->when(
                $period === self::PERIOD_THIS_MONTH,
                fn ($query) => $query->whereBetween('cashouts.paid_at', [$now->copy()->startOfMonth(), $now]),
            )
            ->select([
                'cashouts.user_id',
                'users.username',
                'users.name',
            ])
            ->selectRaw('SUM(ROUND(cashouts.amount * 100, 0)) as wins_cents')
            ->groupBy('cashouts.user_id', 'users.username', 'users.name')
            ->orderByDesc('wins_cents')
            ->orderBy('cashouts.user_id')
            ->limit(10)
            ->get();

        return $rows->values()->map(fn ($row, int $index) => [
            'rank' => $index + 1,
            'player_name' => filled($row->username) ? $row->username : $row->name,
            'wins' => $this->centsToDecimalString((int) $row->wins_cents),
        ])->all();
    }

    public function manualLeaderboard(string $period): array
    {
        $this->assertPeriod($period);

        return PlayerRankEntry::query()
            ->where('period', $period)
            ->whereBetween('rank', [1, 10])
            ->whereNotNull('player_name')
            ->where('player_name', '!=', '')
            ->whereNotNull('wins')
            ->orderBy('rank')
            ->limit(10)
            ->get(['rank', 'player_name', 'wins'])
            ->map(fn (PlayerRankEntry $entry) => [
                'rank' => $entry->rank,
                'player_name' => $entry->player_name,
                'wins' => $entry->wins,
            ])->all();
    }

    private function centsToDecimalString(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function assertPeriod(string $period): void
    {
        if (! in_array($period, self::PERIODS, true)) {
            throw new InvalidArgumentException('Invalid player ranking period.');
        }
    }
}
