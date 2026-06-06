<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Cashout;
use App\Models\Game;
use App\Models\Wallet;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [

            'playersCount' =>
                User::role('player')->count(),

            'agentsCount' =>
                User::role('agent')->count(),

            'pendingDeposits' =>
                Deposit::where('status', 'pending')->count(),

            'pendingCashouts' =>
                Cashout::where('status', 'pending')->count(),

            'gamesCount' =>
                Game::count(),

            'walletsCount' =>
                Wallet::count(),

            'games' =>
                Game::latest()->get(),
        ])
            ->layout('layouts.private');
    }
}
