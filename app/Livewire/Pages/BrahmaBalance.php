<?php

namespace App\Livewire\Pages;

use App\Models\BrahmaDeposit;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use Livewire\Component;
use Livewire\WithFileUploads;

class BrahmaBalance extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public ?string $paymentType = null;
    public ?int $selectedWallet = null;
    public ?string $amount = null;
    public $proofImage;
    public bool $showWalletPreview = false;
    public bool $depositSubmitted = false;
    public ?string $depositReference = null;

    public function openModal(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('player'), 403);

        $this->resetValidation();
        $this->depositSubmitted = false;
        $this->depositReference = null;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset([
            'showModal',
            'paymentType',
            'selectedWallet',
            'amount',
            'proofImage',
            'showWalletPreview',
            'depositSubmitted',
            'depositReference',
        ]);
    }

    public function updatedPaymentType(): void
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

        return Wallet::where('is_active', true)->find($this->selectedWallet);
    }

    public function selectWallet(int $walletId): void
    {
        $wallet = Wallet::where('is_active', true)->findOrFail($walletId);

        $this->selectedWallet = $wallet->id;
    }

    public function submitDeposit(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('player'), 403);

        $this->validate([
            'amount' => 'required|numeric|min:1|max:9999999999.99',
            'paymentType' => 'required|string',
            'selectedWallet' => 'required|exists:wallets,id',
            'proofImage' => 'required|image|max:5120',
        ], [
            'proofImage.required' => 'Payment screenshot is required.',
        ]);

        $wallet = Wallet::with(['walletType'])
            ->where('is_active', true)
            ->where('type', $this->paymentType)
            ->findOrFail($this->selectedWallet);

        $proofPath = $this->proofImage->store('brahma-deposit-proofs', 'public');

        $deposit = BrahmaDeposit::create([
            'user_id' => auth()->id(),
            'wallet_id' => $wallet->id,
            'wallet_type' => $wallet->walletType?->name ?? $wallet->type,
            'wallet_name' => $wallet->name,
            'wallet_account_identifier' => $wallet->account_identifier,
            'amount' => $this->amount,
            'proof_image' => $proofPath,
            'status' => 'pending',
        ]);

        Notification::create([
            'user_id' => auth()->id(),
            'type' => 'brahma_deposit_submitted',
            'title' => 'Brahma Balance Deposit Submitted',
            'message' => 'Deposit for Brahma Balance is submitted successfully. Your balance will be seen after our agent verifies your deposit. Reference: ' . $deposit->reference . '. Amount: $' . number_format((float) $deposit->amount, 2) . '.',
            'action_text' => 'Got It',
            'action_url' => route('player.notifications'),
            'entity_type' => BrahmaDeposit::class,
            'entity_id' => $deposit->id,
            'is_read' => false,
        ]);

        foreach (User::role(['admin', 'agent'])->get() as $receiver) {
            Notification::create([
                'user_id' => $receiver->id,
                'type' => 'brahma_deposit_created',
                'title' => 'Brahma Balance Deposit Received',
                'message' => auth()->user()->name . ' (' . auth()->user()->username . ', P' . auth()->user()->playerProfile?->player_id . ') submitted Brahma Balance deposit of $' . number_format((float) $deposit->amount, 2) . '. Reference: ' . $deposit->reference . '.',
                'action_text' => 'View Brahma Deposits',
                'action_url' => $receiver->hasRole('admin')
                    ? route('admin.brahma.deposits')
                    : route('agent.brahma.deposits'),
                'entity_type' => BrahmaDeposit::class,
                'entity_id' => $deposit->id,
                'created_by' => auth()->id(),
            ]);
        }

        $this->depositSubmitted = true;
        $this->depositReference = $deposit->reference;

        $this->reset([
            'amount',
            'paymentType',
            'selectedWallet',
            'proofImage',
            'showWalletPreview',
        ]);

        $this->dispatch('refreshBell');
    }

    public function refreshBalance(): void
    {
        // The render query refreshes only the authenticated player's balance.
    }

    public function render()
    {
        return view('livewire.pages.brahma-balance', [
            'brahmaBalance' => auth()->check()
                ? User::whereKey(auth()->id())->value('brahma_balance')
                : 0,
            'walletTypes' => $this->showModal
                ? Wallet::where('is_active', true)
                    ->select('type')
                    ->distinct()
                    ->pluck('type')
                : collect(),
        ]);
    }
}
