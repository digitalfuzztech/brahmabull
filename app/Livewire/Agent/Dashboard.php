<?php

namespace App\Livewire\Agent;

use Livewire\Component;
use App\Models\Deposit;
use App\Models\BrahmaDeposit;
use App\Models\Cashout;
use App\Models\Wallet;

class Dashboard extends Component
{
    public function getDepositsProperty()
    {
        $normalDeposits = Deposit::where('verified_by', auth()->id())
            ->latest()
            ->get();

        $brahmaDeposits = BrahmaDeposit::where('verified_by', auth()->id())
            ->latest()
            ->get();

        return $normalDeposits
            ->concat($brahmaDeposits)
            ->sortByDesc('created_at')
            ->values();
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
