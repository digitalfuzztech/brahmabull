<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\WalletType;
use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Models\Notification;

class WalletTypeShow extends Component
{
    use WithFileUploads;

    public $type;
    public $name;

    // wallet modal
    public $showWalletModal = false;
    public $wallet_name;
    public $account_identifier;
    public $qr_image;

    public function mount($type)
    {
        $this->type = WalletType::findOrFail($type);
        $this->name = $this->type->name;
    }

    /* -------------------------
       UPDATE TYPE NAME
    -------------------------*/
    public function updateType()
    {
        $oldTypeName = $this->type->name;
        $agentName = $this->type->agent->name;
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $this->type->update([
            'wallet_agent_id' => $this->type->agent->id,
            'name' => $this->name,
        ]);

        $admins = \App\Models\User::role('admin')->pluck('id');
        $agents = \App\Models\User::role('agent')->pluck('id');

        $receivers = $admins->merge($agents);

        foreach ($receivers as $userId) {

            \App\Models\Notification::create([
                'user_id' => $userId,
                'type' => 'wallet',
                'title' => 'Wallet Type Updated',
                'message' => "{$oldTypeName} for Agent {$agentName} has been updated to {$this->name} by " . auth()->user()->name,
                'action_text' => 'Go To Wallets',
                'action_url' => $userId == auth()->id()
                    ? (auth()->user()->hasRole('admin')
                        ? '/admin/wallets'
                        : '/agent/wallets')
                    : null,
            ]);

        }
        session()->flash('success', 'Wallet Type Updated Successfully!');
    }

    /* -------------------------
       CREATE WALLET
    -------------------------*/
    public function createWallet()
    {
        $this->validate([
            'wallet_name' => 'required|string|max:255',
            'account_identifier' => 'required|string|max:255',
            'qr_image' => 'nullable|image|max:5120',
        ]);

        $qrPath = null;

        if ($this->qr_image) {
            $qrPath = $this->qr_image->store('wallet-qr', 'public');
        }
        Wallet::create([
            'wallet_type_id' => $this->type->id,
            'wallet_agent_id' => $this->type->agent->id,

            'type' => $this->type->name, // keep for player deposit modal

            'name' => $this->wallet_name,
            'account_identifier' => $this->account_identifier,
            'qr_image' => $qrPath,

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
                'title' => 'New Wallet Added',
                'message' => "{$this->wallet_name} - {$this->type->name} for agent {$this->type->agent->name} has been created by " . auth()->user()->name,
                'action_text' => 'Go To Wallets',
                'action_url' => $userId == auth()->id()
                    ? (auth()->user()->hasRole('admin')
                        ? '/admin/wallets'
                        : '/agent/wallets')
                    : null,
            ]);

        }

        $this->reset([
            'wallet_name',
            'account_identifier',
            'qr_image',
            'showWalletModal'
        ]);
    }

    /* -------------------------
       LIST WALLETS
    -------------------------*/
    public function getWalletsProperty()
    {
        return Wallet::with([
            'creator',
            'updater'
        ])
            ->where('wallet_type_id', $this->type->id)
            ->latest()
            ->get();
    }
    public function toggleWallet($walletId)
    {
        $wallet = Wallet::findOrFail($walletId);

        $newStatus = !$wallet->is_active;

        $wallet->update([
            'is_active' => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        $status = $newStatus ? 'enabled' : 'disabled';

        $admins = \App\Models\User::role('admin')->get();
        $agents = \App\Models\User::role('agent')->get();

        $receivers = $admins->merge($agents);

        foreach ($receivers as $receiver) {

            \App\Models\Notification::create([
                'user_id' => $receiver->id,
                'type' => 'wallet',
                'title' => 'Wallet Updated',
                'message' => "{$wallet->name} has been {$status} by " . auth()->user()->name,
                'action_text' => 'Go To Wallets',
                'action_url' => $receiver->hasRole('admin')
                    ? route('admin.wallets')
                    : route('agent.wallets'),
            ]);
        }
    }
    public function render()
    {
        return view('livewire.admin.wallet-type-show')
            ->layout('layouts.private');
    }
}
