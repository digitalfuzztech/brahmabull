<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use App\Models\Game;
use App\Models\Wallet;
use Livewire\WithFileUploads;
use App\Models\Deposit;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Models\User;


class Games extends Component
{
    use WithFileUploads;
    public $selectedGame = null;
    public $showModal = false;
    public $paymentMethod = null;
    public $paymentType = null;
    public $selectedWallet = null;
    public $amount = null;
    public $showWalletPreview = false;
    public $proofImage;

    public $depositSubmitted = false;
    public $depositReference = null;

    public function openPlayModal($gameId)
    {
        $game = Game::findOrFail($gameId);

        $this->depositSubmitted = false;

        $this->selectedGame = $game;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;

        $this->selectedGame = null;
        $this->paymentType = null;
        $this->selectedWallet = null;
        $this->amount = null;
        $this->showWalletPreview = false;
        $this->proofImage = null;
        $this->depositSubmitted = false;
        $this->depositReference = null;
    }

    public function updatedPaymentType()
    {
        $this->selectedWallet = null;
    }
    public function getFilteredWalletsProperty()
    {
        if (!$this->paymentType) {
            return collect();
        }

        return Wallet::where('is_active', true)
            ->where('type', $this->paymentType)
            ->get();
    }

    public function getSelectedWalletModelProperty()
    {
        if (!$this->selectedWallet) {
            return null;
        }

        return Wallet::find($this->selectedWallet);
    }

    public function submitDeposit()
    {
        $this->validate([
            'amount' => 'required|numeric|min:1',
            'paymentType' => 'required',
            'selectedWallet' => 'required',
            'proofImage' => 'required|image|max:5120',
        ]);

        $proofPath = $this->proofImage->store('deposit-proofs', 'public');

        $deposit = Deposit::create([
            'user_id' => auth()->id(),
            'game_id' => $this->selectedGame->id,
            'wallet_type' => $this->paymentType,
            'wallet_id' => $this->selectedWallet,
            'amount' => $this->amount,
            'proof_image' => $proofPath,
            'status' => 'pending',
        ]);

        Notification::create([
            'user_id' => auth()->id(),

            'type' => 'deposit_submitted',

            'title' => 'Deposit Submitted',

            'message' =>
                'Your deposit [' .
                $deposit->reference .
                '] of $' .
                $deposit->amount .
                ' for ' .
                $this->selectedGame->name .
                ' is submitted. Our Agent will verify it and inform you shortly. Please wait for a few minutes.',

            'action_text' => 'Got It',

            'action_url' => route('player.notifications'),

            'is_read' => false,
        ]);

        $adminsAgents = User::role(['admin', 'agent'])->get();

        foreach ($adminsAgents as $receiver) {

            Notification::create([
                'user_id' => $receiver->id,

                'type' => 'deposit_created',
                'title' => 'Deposit Received',
                'message' => auth()->user()->name .
                    ' (P' . auth()->user()->playerProfile?->player_id . ') deposited $' .
                    $deposit->amount .
                    ' for game ' . $this->selectedGame->name,

                'action_text' => 'View Deposits',


                'action_url' =>
                    $receiver->hasRole('admin')
                        ? route('admin.deposits')
                        : route('agent.deposits'),
            ]);
        }

        $this->depositSubmitted = true;
      $this->depositReference = $deposit->reference;


        $this->reset([
            'amount',
            'paymentType',
            'selectedWallet',
            'proofImage',
        ]);
    }
    public function selectWallet($walletId)
    {
        $this->selectedWallet = $walletId;
    }
    public function render()
    {
        return view('livewire.pages.games', [
            'games' => Game::where('is_active', true)->get(),
            'walletTypes' => Wallet::where('is_active', true)
                ->select('type')
                ->distinct()
                ->pluck('type'),
        ])->layout('layouts.public');
    }
}
