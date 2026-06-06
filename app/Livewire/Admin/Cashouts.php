<?php

namespace App\Livewire\Admin;

use App\Models\Cashout;
use App\Models\Game;
use App\Models\Wallet;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Notification;
use App\Models\User;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

class Cashouts extends Component
{
    use WithFileUploads;
    use WithPagination, WithFileUploads;

    protected $paginationTheme = 'tailwind';
    public $search = '';
    public $searchDate = '';
    public $statusFilter = '';
    public $gameFilter = '';
    public $walletTypeFilter = '';
    public $walletFilter = '';
    public $selectedCashout = null;

    public $status;
    public $remarks;

    public $wallet_id;

    public $payment_proof;

    public $proofPreview = null;

    public function getCashoutsProperty()
    {
        $query = Cashout::with([
            'user',
            'game',
            'gameAccount',
            'wallet.walletType',
            'wallet.walletAgent',
            'verifier',
        ]);

        if ($this->searchDate) {
            $query->whereDate(
                'created_at',
                $this->searchDate
            );
        }

        if ($this->statusFilter) {
            $query->where(
                'status',
                $this->statusFilter
            );
        }

        if ($this->gameFilter) {
            $query->where(
                'game_id',
                $this->gameFilter
            );
        }

        if ($this->search) {

            $search = $this->search;

            $query->where(function ($q) use ($search) {

                $q->where(
                    'reference',
                    'like',
                    "%{$search}%"
                )

                    ->orWhereHas('user', function ($u) use ($search) {

                        $u->where(
                            'name',
                            'like',
                            "%{$search}%"
                        );
                    })

                    ->orWhereHas('gameAccount', function ($ga) use ($search) {

                        $ga->where(
                            'game_username',
                            'like',
                            "%{$search}%"
                        );
                    });
            });
        }

        $grouped = $query
            ->latest()
            ->get()
            ->groupBy(
                fn ($c) => $c->created_at->format('Y-m-d')
            );

        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $perPage = 3;

        $dates = $grouped->keys()->values();

        $paginatedDates = $dates->slice(
            ($currentPage - 1) * $perPage,
            $perPage
        )->values();

        $filtered = collect($paginatedDates)
            ->mapWithKeys(fn ($date) => [
                $date => $grouped[$date]
            ]);

        return new LengthAwarePaginator(
            $filtered,
            $grouped->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function getGamesProperty()
    {
        return Game::orderBy('name')->get();
    }
    public function getWalletsProperty()
    {
        return Wallet::with(['walletType'])
            ->where('is_active', true)
            ->get();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSearchDate()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingGameFilter()
    {
        $this->resetPage();
    }

    public function openModal($cashoutId)
    {
        $this->resetValidation();

        $this->selectedCashout = Cashout::findOrFail($cashoutId);

        $this->status = $this->selectedCashout->status ?? 'pending';
        $this->remarks = $this->selectedCashout->remarks;

        $this->wallet_id = $this->selectedCashout->wallet_id;
        $this->payment_proof = null;
    }

    public function closeModal()
    {
        $this->reset([
            'selectedCashout',
            'status',
            'remarks',
            'wallet_id',
            'payment_proof',
        ]);
    }

    public function processCashout()
    {
        $cashout = Cashout::findOrFail(
            $this->selectedCashout->id
        );

        $this->validate([
            'status' => 'required|in:pending,approved,rejected,paid',
        ]);

        $proofPath = $cashout->payment_proof;

        /*
        |--------------------------------------------------------------------------
        | PAID STATUS
        |--------------------------------------------------------------------------
        */
        if ($this->status === 'paid') {

            $this->validate([
                'wallet_id' => 'required|exists:wallets,id',
            ]);

            if ($this->payment_proof) {

                $this->validate([
                    'payment_proof' => 'image|max:4096'
                ]);

                $proofPath = $this->payment_proof->store(
                    'cashout-proofs',
                    'public'
                );
            }

            $cashout->update([
                'status' => 'paid',
                'wallet_id' => $this->wallet_id,
                'remarks' => $this->remarks,

                // handled time remains untouched
                'verified_by' => auth()->id(),
                'verified_at' => now(),

                'paid_at' => $this->status === 'paid'
                    ? now()
                    : $cashout->paid_at,


                'payment_proof' => $proofPath,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PENDING / APPROVED / REJECTED
        |--------------------------------------------------------------------------
        */
        else {

            $cashout->update([
                'status' => $this->status,
                'remarks' => $this->remarks,

                // handler
                'verified_by' => auth()->id(),

                // handled at
                'verified_at' => now(),
            ]);
        }

        $admin = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | 1. ADMIN / AGENT NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $adminsAgents = User::role(['admin', 'agent'])->get();

        foreach ($adminsAgents as $receiver) {

            Notification::create([
                'user_id' => $receiver->id,
                'type' => 'cashout_admin',

                'title' =>
                    'Cashout [' . $cashout->reference . '] processed',

                'message' =>
                    'Cashout of ' . $cashout->amount .
                    ' for player ' . $cashout->user->name .
                    ' (' . $cashout->user->playerProfile?->player_id . ')' .
                    ' game ' . $cashout->game?->name .
                    ' (' . $cashout->gameAccount?->game_username . ')' .
                    ' has been ' . $this->status .
                    ' by ' . $admin->name,

                'action_text' => 'View Cashouts',
                'action_url' => route('admin.cashouts'),

                'is_read' => false,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. PLAYER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        if ($this->status === 'paid') {

            Notification::create([
                'user_id' => $cashout->user_id,

                'type' => 'cashout_paid',

                'title' => 'Withdrawal Paid',

                'message' =>
                    'Congratulations!!! Your Withdrawal request [' .
                    $cashout->reference .
                    '] of $' .
                    $cashout->amount .
                    ' for ' .
                    $cashout->gameAccount?->game_username .
                    ' has been verified and paid.',

                'action_text' => 'Play',

                'action_url' => route('games'),

                'is_read' => false,
            ]);

        }

        elseif ($this->status === 'rejected') {

            Notification::create([
                'user_id' => $cashout->user_id,

                'type' => 'cashout_rejected',

                'title' => 'Withdrawal Rejected',

                'message' =>
                    'Your Withdrawal Request [' .
                    $cashout->reference .
                    '] of $' .
                    $cashout->amount .
                    ' for ' .
                    $cashout->gameAccount?->game_username .
                    ' is rejected. Please contact the agent.',

                'action_text' => 'Play',

                'action_url' => route('games'),

                'is_read' => false,
            ]);

        }


        $this->closeModal();

        session()->flash(
            'success',
            'Cashout updated successfully.'
        );
    }
    public function openProof($image)
    {
        $this->proofPreview = $image;
    }

    public function closeProof()
    {
        $this->proofPreview = null;
    }

    public function render()
    {
        return view('livewire.admin.cashouts')
            ->layout('layouts.private');
    }
}
