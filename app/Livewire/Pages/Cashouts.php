<?php

namespace App\Livewire\Pages;

use App\Models\Cashout;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;


class Cashouts extends Component
{
    use WithFileUploads;

    public $game_id = '';
    public $game_account_id = '';

    public $amount = '';

    public $wallet_type = '';

    public $wallet_address = '';

    public $qr_image;

    public $cashoutSubmitted = false;

    public $cashoutReference = null;

    public function getPlayerAccountsProperty()
    {
        if (!$this->game_id) {
            return collect();
        }

        return GameAccount::where('user_id', auth()->id())
            ->where('game_id', $this->game_id)
            ->get();
    }

    public function updatedGameId()
    {
        $this->game_account_id = '';
    }

    public function submit()
    {
        $this->validate([
            'game_id' => 'required',
            'game_account_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'wallet_type' => 'required',
        ]);

        if (!$this->wallet_address && !$this->qr_image) {
            $this->addError(
                'wallet_address',
                'Provide Wallet Address or QR Code.'
            );

            return;
        }

        $qrPath = null;

        if ($this->qr_image) {

            $this->validate([
                'qr_image' => 'image|max:5120'
            ]);

            $qrPath = $this->qr_image->store(
                'cashout-qr',
                'public'
            );
        }

        $cashout = Cashout::create([
            'reference' => 'CASH-' . strtoupper(Str::random(8)),

            'user_id' => auth()->id(),

            'game_id' => $this->game_id,

            'game_account_id' => $this->game_account_id,

            'amount' => $this->amount,

            'wallet_type' => $this->wallet_type,

            'wallet_address' => $this->wallet_address,

            'qr_image' => $qrPath,

            'status' => 'pending',
        ]);

        Notification::create([
            'user_id' => auth()->id(),

            'type' => 'cashout_submitted',

            'title' => 'Withdrawal Request Submitted',

            'message' =>
                'Your Withdrawal Request [' .
                $cashout->reference .
                '] of $' .
                $cashout->amount .
                ' for ' .
                $cashout->gameAccount?->game_username .
                ' is submitted. Our agent will verify it and inform you shortly.',

            'action_text' => 'Got It',

            'action_url' => route('player.notifications'),

            'is_read' => false,
        ]);


        $adminsAgents = User::role(['admin', 'agent'])->get();

        foreach ($adminsAgents as $receiver) {

            Notification::create([
                'user_id' => $receiver->id,

                'type' => 'cashout_created',
                'title' => 'Cashout Requested',
                'message' =>
                    auth()->user()->name .
                    ' (P' . auth()->user()->playerProfile?->player_id . ') requested withdrawal of $' .
                    $cashout->amount .
                    ' for game ' .
                    $cashout->game?->name .
                    ' (' .
                    $cashout->gameAccount?->game_username .
                    ')',

                'action_text' => 'View Cashouts',

                'action_url' =>
                    $receiver->hasRole('admin')
                        ? route('admin.cashouts')
                        : route('agent.cashouts'),
            ]);
        }

        $this->cashoutSubmitted = true;

        $this->cashoutReference = $cashout->reference;

        $this->reset([
            'game_id',
            'game_account_id',
            'amount',
            'wallet_type',
            'wallet_address',
            'qr_image',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.cashouts', [
            'games' => Game::where('is_active', true)->get(),

            'walletTypes' => Wallet::where('is_active', true)
                ->select('type')
                ->distinct()
                ->pluck('type'),
        ])->layout('layouts.public');
    }
}
