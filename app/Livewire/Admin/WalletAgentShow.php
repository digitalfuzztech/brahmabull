<?php

namespace App\Livewire\Admin;

use App\Models\Notification;
use Livewire\Component;
use App\Models\WalletAgent;
use App\Models\WalletType;
use Illuminate\Support\Str;

class WalletAgentShow extends Component
{
    public $agent;
    public $name;

    public $showTypeModal = false;
    public $typeName;

    public function mount($agent)
    {
        $this->agent = WalletAgent::findOrFail($agent);
        $this->name = $this->agent->name;
    }

    /* --------------------------
       UPDATE AGENT NAME
    ---------------------------*/
    public function updateAgent()
    {
        $oldAgentName = $this->agent->name;
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $this->agent->update([
            'name' => $this->name,
        ]);


        $admins = \App\Models\User::role('admin')->pluck('id');
        $agents = \App\Models\User::role('agent')->pluck('id');

        $receivers = $admins->merge($agents);

        foreach ($receivers as $userId) {

            \App\Models\Notification::create([
                'user_id' => $userId,
                'type' => 'wallet',
                'title' => 'Wallet Agent Updated',
                'message' => "Wallet Agent {$oldAgentName} has been updated to {$this->name} by " . auth()->user()->name,
                'action_text' => 'Go To Wallets',
                'action_url' => $userId == auth()->id()
                    ? (auth()->user()->hasRole('admin')
                        ? '/admin/wallets/{$agent->id}'
                        : '/agent/wallets/{$agent->id}')
                    : null,
            ]);

        }

        session()->flash('success', $this->agent->name . ' Updated Successfully!');
    }

    /* --------------------------
       ADD WALLET TYPE
    ---------------------------*/
    public function createType()
    {
        $this->validate([
            'typeName' => 'required|string|max:255',
        ]);

       WalletType::create([
            'wallet_agent_id' => $this->agent->id,
            'name' => $this->typeName,
            'slug' => Str::slug($this->typeName) . '-' . Str::random(4),
        ]);

        $admins = \App\Models\User::role('admin')->pluck('id');
        $agents = \App\Models\User::role('agent')->pluck('id');

        $receivers = $admins->merge($agents);

        foreach ($receivers as $userId) {

            \App\Models\Notification::create([
                'user_id' => $userId,
                'type' => 'wallet',
                'title' => 'New Wallet Type',
                'message' => "{$this->typeName} for agent {$this->agent->name} has been created by " . auth()->user()->name,
                'action_text' => 'Go To Wallets',
                'action_url' => $userId == auth()->id()
                    ? (auth()->user()->hasRole('admin')
                        ? "/admin/wallets/agent-{$this->agent->id}"
                        : "/agent/wallets/agent-{$this->agent->id}")
                    : null,
            ]);

        }


        $this->reset(['typeName', 'showTypeModal']);
    }

    /* --------------------------
       TYPES LIST
    ---------------------------*/
    public function getTypesProperty()
    {
        return WalletType::where('wallet_agent_id', $this->agent->id)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.wallet-agent-show')
            ->layout('layouts.private');
    }
}
