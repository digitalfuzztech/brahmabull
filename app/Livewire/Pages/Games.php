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
use App\Models\BrahmaPlayRequest;


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
    public $playModalTab = 'payment';
    public $pointsToLoad = null;

    public $depositSubmitted = false;
    public $depositReference = null;
    public $brahmaPlaySubmitted = false;
    public $brahmaPlayReference = null;

    public function openPlayModal($gameId)
    {
        $game = Game::findOrFail($gameId);

        $this->depositSubmitted = false;
        $this->brahmaPlaySubmitted = false;
        $this->playModalTab = 'payment';

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
        $this->playModalTab = 'payment';
        $this->pointsToLoad = null;
        $this->depositSubmitted = false;
        $this->depositReference = null;
        $this->brahmaPlaySubmitted = false;
        $this->brahmaPlayReference = null;
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

    public function submitBrahmaPlay()
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('player'), 403);

        $this->validate([
            'pointsToLoad' => 'required|numeric|min:1|max:9999999999.99',
        ]);

        $game = Game::where('is_active', true)->findOrFail($this->selectedGame?->id);
        $player = User::whereKey(auth()->id())->firstOrFail();

        if ((float) $this->pointsToLoad > (float) $player->brahma_balance) {
            $this->addError('pointsToLoad', "You don't have sufficient balance.");

            return;
        }

        $playRequest = BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => $this->pointsToLoad,
            'balance_at_submission' => $player->brahma_balance,
            'status' => 'pending',
        ]);

        Notification::create([
            'user_id' => $player->id,
            'type' => 'brahma_play_submitted',
            'title' => 'Play Request Submitted',
            'message' => 'Your Play request is submitted. Please wait for a few minutes for game username, game password and game link. Game: ' . $game->name . '. Points: ' . number_format((float) $playRequest->points_to_load, 2) . '. Reference: ' . $playRequest->reference . '.',
            'action_text' => 'Play More Games',
            'action_url' => route('games'),
            'entity_type' => BrahmaPlayRequest::class,
            'entity_id' => $playRequest->id,
            'game_id' => $game->id,
            'is_read' => false,
        ]);

        foreach (User::role(['admin', 'agent'])->get() as $receiver) {
            Notification::create([
                'user_id' => $receiver->id,
                'type' => 'brahma_play_created',
                'title' => 'Brahma Play Request Received',
                'message' => $player->name . ' (' . $player->username . ', P' . $player->playerProfile?->player_id . ') requested ' . number_format((float) $playRequest->points_to_load, 2) . ' points for ' . $game->name . '. Reference: ' . $playRequest->reference . '.',
                'action_text' => 'View Brahma Plays',
                'action_url' => $receiver->hasRole('admin')
                    ? route('admin.brahma.plays')
                    : route('agent.brahma.plays'),
                'entity_type' => BrahmaPlayRequest::class,
                'entity_id' => $playRequest->id,
                'game_id' => $game->id,
                'created_by' => $player->id,
            ]);
        }

        $this->brahmaPlaySubmitted = true;
        $this->brahmaPlayReference = $playRequest->reference;
        $this->pointsToLoad = null;
        $this->dispatch('refreshBell');
    }

    public function render()
    {
        return view('livewire.pages.games', [
            'games' => Game::where('is_active', true)->get(),
            'playerBalance' => auth()->user()?->fresh()?->brahma_balance ?? 0,
            'walletTypes' => Wallet::where('is_active', true)
                ->select('type')
                ->distinct()
                ->pluck('type'),
        ])->layout('layouts.public');
    }
}
