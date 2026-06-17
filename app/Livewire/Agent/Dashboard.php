<?php

namespace App\Livewire\Agent;

use Livewire\Component;
use App\Models\Deposit;
use App\Models\Cashout;
use App\Models\Wallet;

class Dashboard extends Component
{
    public function getDepositsProperty()
    {
        return Deposit::where('verified_by', auth()->id())
            ->latest()
            ->get();
    }

    public function getCashoutsProperty()
    {
        return Cashout::where('verified_by', auth()->id())
            ->latest()
            ->get();
    }

    public function getWalletsProperty()
    {
        return Wallet::where('created_by', auth()->id())
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.agent.dashboard')
            ->layout('layouts.private');
    }
}
