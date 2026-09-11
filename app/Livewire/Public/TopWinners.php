<?php

namespace App\Livewire\Public;

use App\Services\PlayerRankingService;
use Livewire\Component;

class TopWinners extends Component
{
    public string $period = PlayerRankingService::PERIOD_LAST_7_DAYS;

    public function setPeriod(string $period): void
    {
        abort_unless(in_array($period, PlayerRankingService::PERIODS, true), 404);

        $this->period = $period;
    }

    public function render(PlayerRankingService $rankings)
    {
        return view('livewire.public.top-winners', [
            'rankings' => $rankings->leaderboard($this->period),
        ]);
    }
}
