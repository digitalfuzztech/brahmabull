<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Cashout;
use App\Models\Wallet;

class AgentShow extends Component
{
    public $agent;

    public function mount($id)
    {
        $this->agent = User::findOrFail($id);
    }

    public function getDepositsProperty()
    {
        return Deposit::where('verified_by', $this->agent->id)
            ->latest()
            ->get();
    }

    public function getCashoutsProperty()
    {
        return Cashout::where('verified_by', $this->agent->id)
            ->latest()
            ->get();
    }

    public function getWalletsProperty()
    {
        return Wallet::where('created_by', $this->agent->id)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.agent-show')
            ->layout('layouts.private');
    }
}
