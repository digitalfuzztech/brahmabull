<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\WalletAgent;
use Illuminate\Support\Str;
use App\Models\Notification;

class WalletAgents extends Component
{
    public $showAddModal = false;
    public $name;

    public function getAgentsProperty()
    {
        return WalletAgent::latest()->get();
    }

    public function createAgent()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        WalletAgent::create([
            'name' => $this->name,
            'slug' => Str::slug($this->name) . '-' . Str::random(4),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);


        $admins = \App\Models\User::role('admin')->pluck('id');
        $agents = \App\Models\User::role('agent')->pluck('id');

        $receivers = $admins->merge($agents);

        foreach ($receivers as $userId) {

            \App\Models\Notification::create([
                'user_id' => $userId,
                'type' => 'wallet',
                'title' => 'New Wallet Agent',
                'message' => "Wallet Agent {$this->name} has been added by " . auth()->user()->name,
                'action_text' => 'Go To Wallets',
                'action_url' => $userId == auth()->id()
                    ? (auth()->user()->hasRole('admin')
                        ? '/admin/wallets'
                        : '/agent/wallets')
                    : null,
            ]);

        }

        $this->reset(['name', 'showAddModal']);
    }

    public function render()
    {
        return view('livewire.admin.wallet-agents')
            ->layout('layouts.private');
    }
}
