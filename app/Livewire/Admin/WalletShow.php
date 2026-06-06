<?php

namespace App\Livewire\Admin;

use App\Models\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\Cashout;
use Illuminate\Support\Facades\DB;

class WalletShow extends Component
{
    use WithFileUploads;

    public $wallet;

    public $name;
    public $account_identifier;
    public $qr_image;

    public function mount($wallet)
    {
        $this->wallet = Wallet::findOrFail($wallet);

        $this->name = $this->wallet->name;
        $this->account_identifier = $this->wallet->account_identifier;
    }

    /* --------------------------
       UPDATE WALLET
    --------------------------*/
    public function updateWallet()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'account_identifier' => 'required|string|max:255',
            'qr_image' => 'nullable|image|max:5120',
        ]);

        $qrPath = $this->wallet->qr_image;

        if ($this->qr_image) {
            $qrPath = $this->qr_image->store('wallet-qr', 'public');
        }

        $oldName = $this->wallet->name;
        $oldIdentifier = $this->wallet->account_identifier;
        $oldQr = $this->wallet->qr_image;

        $walletType = $this->wallet->walletType->name;
        $walletAgent = $this->wallet->walletType->agent->name;


        $this->wallet->update([
            'name' => $this->name,
            'account_identifier' => $this->account_identifier,
            'qr_image' => $qrPath,
            'updated_by' => auth()->id(),
        ]);

        $nameChanged = $oldName !== $this->name;
        $identifierChanged = $oldIdentifier !== $this->account_identifier;
        $qrChanged = $oldQr !== $qrPath;

        $totalChanges =
            ($nameChanged ? 1 : 0) +
            ($identifierChanged ? 1 : 0) +
            ($qrChanged ? 1 : 0);

        $admins = \App\Models\User::role('admin')->pluck('id');
        $agents = \App\Models\User::role('agent')->pluck('id');

        if ($totalChanges > 1) {

            $title = 'Wallet Updated';

            $message =
                "{$this->name} - {$walletType} for agent {$walletAgent} has been updated by "
                . auth()->user()->name;

        }
        elseif ($nameChanged && $totalChanges === 1) {

            $title = 'Wallet Updated';

            $message =
                "{$oldName} has been updated by "
                . auth()->user()->name .
                " to {$this->name}";

        }
        elseif ($qrChanged) {

            $title = "QR for {$this->name} updated";

            $message =
                "QR Code for {$this->name} - {$walletType} for agent {$walletAgent} has been updated by "
                . auth()->user()->name;

        }
        elseif ($identifierChanged) {

            $title = "Cashtag for {$this->name} updated";

            $message =
                "Cashtag for {$this->name} - {$walletType} for agent {$walletAgent} has been updated by "
                . auth()->user()->name;

        }
        else {

            $title = 'Wallet Updated';

            $message =
                "{$this->name} - {$walletType} for agent {$walletAgent} has been updated by "
                . auth()->user()->name;
        }


        $receivers = $admins->merge($agents);

        foreach ($receivers as $userId) {

            \App\Models\Notification::create([
                'user_id' => $userId,
                'type' => 'wallet',
                'title' => $title,
                'message' => $message,
                'action_text' => 'Go To Wallets',
                'action_url' => route('admin.wallets'),
            ]);

        }
        session()->flash('success', $this->wallet->name . ' Updated Successfully!');
    }

    /* --------------------------
       REPORTS
    --------------------------*/

    public function getDepositsProperty()
    {
        return Deposit::where('wallet_id', $this->wallet->id)
            ->where('status', 'verified')
            ->latest()
            ->get();
    }

    public function getCashoutsProperty()
    {
        return Cashout::where('wallet_id', $this->wallet->id)
            ->where('status', 'paid')
            ->latest()
            ->get();
    }

    public function getTopPlayersProperty()
    {
        return DB::table('deposits')
            ->select(
                'user_id',
                DB::raw('COUNT(id) as total_deposits'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('wallet_id', $this->wallet->id)
            ->where('status', 'verified')
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.wallet-show')
            ->layout('layouts.private');
    }
}
