<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Deposit;
use App\Models\Cashout;

class TopPlayers extends Component
{
    public $search = '';

    public $gameFilter = '';

    public function getGamesProperty()
    {
        return Game::orderBy('name')->get();
    }

    public function getRankingsProperty()
    {
        $rows = GameAccount::query()

            ->with([
                'user.playerProfile',
                'game',
            ])

            ->when($this->gameFilter, function ($q) {

                $q->where(
                    'game_id',
                    $this->gameFilter
                );

            })

            ->get()

            ->map(function ($account) {

                $depositCount = Deposit::query()

                    ->where('user_id', $account->user_id)
                    ->where('game_id', $account->game_id)
                    ->where('status', 'verified')
                    ->count();

                $depositTotal = Deposit::query()

                    ->where('user_id', $account->user_id)
                    ->where('game_id', $account->game_id)
                    ->where('status', 'verified')
                    ->sum('amount');

                $withdrawCount = Cashout::query()

                    ->where('user_id', $account->user_id)
                    ->where('game_id', $account->game_id)
                    ->where('status', 'paid')
                    ->count();

                $withdrawTotal = Cashout::query()

                    ->where('user_id', $account->user_id)
                    ->where('game_id', $account->game_id)
                    ->where('status', 'paid')
                    ->sum('amount');

                $pointsUsed = Deposit::query()

                    ->where('user_id', $account->user_id)
                    ->where('game_id', $account->game_id)
                    ->where('status', 'verified')
                    ->sum('game_points_loaded');

                return (object) [

                    'account' => $account,

                    'points_used' => $pointsUsed,

                    'deposit_count' => $depositCount,

                    'deposit_total' => $depositTotal,

                    'withdraw_count' => $withdrawCount,

                    'withdraw_total' => $withdrawTotal,

                    'net' => $depositTotal - $withdrawTotal,

                ];

            });

        if ($this->search) {

            $search = strtolower($this->search);

            $rows = $rows->filter(function ($row) use ($search) {

                return str_contains(
                        strtolower($row->account->game_username),
                        $search
                    )

                    ||

                    str_contains(
                        strtolower($row->account->user->name),
                        $search
                    )

                    ||

                    str_contains(
                        (string) optional(
                            $row->account->user->playerProfile
                        )->player_id,
                        $search
                    );
            });
        }

        return $rows

            ->sortByDesc('points_used')

            ->values();
    }

    public function getTopTenProperty()
    {
        return \App\Models\User::role('player')

            ->with('playerProfile')

            ->get()

            ->map(function ($player) {

                $depositCount = \App\Models\Deposit::where(
                    'user_id',
                    $player->id
                )
                    ->where('status', 'verified')
                    ->count();

                $depositTotal = \App\Models\Deposit::where(
                    'user_id',
                    $player->id
                )->where('status', 'verified')
                    ->sum('amount');

                $pointsUsed = \App\Models\Deposit::where(
                    'user_id',
                    $player->id
                )->where('status', 'verified')
                    ->sum('game_points_loaded');

                return (object)[

                    'player' => $player,

                    'deposit_count' => $depositCount,

                    'deposit_total' => $depositTotal,

                    'points_used' => $pointsUsed,

                ];

            })

            ->sortByDesc('points_used')

            ->take(10)

            ->values();
    }

    public function render()
    {
        return view(
            'livewire.admin.top-players'
        )->layout('layouts.private');
    }
}
