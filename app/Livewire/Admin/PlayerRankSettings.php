<?php

namespace App\Livewire\Admin;

use App\Models\PlayerRankEntry;
use App\Models\PlayerRankSetting;
use App\Models\User;
use App\Services\PlayerRankingService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PlayerRankSettings extends Component
{
    public string $mode = PlayerRankSetting::MODE_MANUAL;

    public string $period = PlayerRankingService::PERIOD_LAST_7_DAYS;

    public array $manualRows = [];

    public function mount(): void
    {
        $this->admin();

        $this->mode = PlayerRankSetting::query()->whereKey(1)->value('mode')
            ?? PlayerRankSetting::MODE_MANUAL;

        $this->loadManualRows();
    }

    public function setPeriod(string $period): void
    {
        $this->admin();
        abort_unless(in_array($period, PlayerRankingService::PERIODS, true), 404);

        $this->period = $period;
    }

    public function save(): void
    {
        $this->admin();

        $rules = [
            'mode' => ['required', 'in:automatic,manual'],
        ];

        if ($this->mode === PlayerRankSetting::MODE_MANUAL) {
            foreach (PlayerRankingService::PERIODS as $period) {
                foreach (range(0, 9) as $index) {
                    $rules["manualRows.$period.$index.player_name"] = ['nullable', 'string', 'max:100'];
                    $rules["manualRows.$period.$index.wins"] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];
                }
            }
        }

        $this->validate($rules);

        if ($this->mode === PlayerRankSetting::MODE_MANUAL && ! $this->validateCompleteRows()) {
            return;
        }

        DB::transaction(function (): void {
            PlayerRankSetting::query()->updateOrCreate(
                ['id' => 1],
                ['mode' => $this->mode],
            );

            if ($this->mode !== PlayerRankSetting::MODE_MANUAL) {
                return;
            }

            foreach (PlayerRankingService::PERIODS as $period) {
                foreach ($this->manualRows[$period] as $index => $row) {
                    $rank = $index + 1;
                    $playerName = trim((string) ($row['player_name'] ?? ''));
                    $wins = $row['wins'] ?? null;

                    if ($playerName === '' && ($wins === null || $wins === '')) {
                        PlayerRankEntry::query()
                            ->where('period', $period)
                            ->where('rank', $rank)
                            ->delete();

                        continue;
                    }

                    PlayerRankEntry::query()->updateOrCreate(
                        ['period' => $period, 'rank' => $rank],
                        ['player_name' => $playerName, 'wins' => $wins],
                    );
                }
            }
        });

        session()->flash('success', 'Player rank settings saved.');
    }

    public function render()
    {
        $this->admin();

        return view('livewire.admin.player-rank-settings')
            ->layout('layouts.private');
    }

    private function loadManualRows(): void
    {
        $entries = PlayerRankEntry::query()->get()->keyBy(
            fn (PlayerRankEntry $entry) => $entry->period.'.'.$entry->rank,
        );

        foreach (PlayerRankingService::PERIODS as $period) {
            $this->manualRows[$period] = [];

            foreach (range(1, 10) as $rank) {
                $entry = $entries->get($period.'.'.$rank);
                $this->manualRows[$period][] = [
                    'player_name' => $entry?->player_name ?? '',
                    'wins' => $entry?->wins ?? '',
                ];
            }
        }
    }

    private function validateCompleteRows(): bool
    {
        $valid = true;

        foreach (PlayerRankingService::PERIODS as $period) {
            foreach ($this->manualRows[$period] as $index => $row) {
                $playerName = trim((string) ($row['player_name'] ?? ''));
                $wins = $row['wins'] ?? null;
                $hasName = $playerName !== '';
                $hasWins = $wins !== null && $wins !== '';

                if ($hasName && ! $hasWins) {
                    $this->addError("manualRows.$period.$index.wins", 'Wins is required when a player name is entered.');
                    $valid = false;
                }

                if ($hasWins && ! $hasName) {
                    $this->addError("manualRows.$period.$index.player_name", 'Player name is required when wins is entered.');
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('admin'), 403);

        return $user;
    }
}
