<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Deposit;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use Livewire\WithPagination;
use App\Models\Game;
use App\Models\Wallet;
use App\Models\WalletType;
use Illuminate\Pagination\LengthAwarePaginator;

class Deposits extends Component
{
    use WithPagination;
    public $selectedDeposit = null;

    public $status;
    public $game_username;
    public $game_password;
    public $admin_notes;
    public $game_points_loaded;
    public $proofPreview = null;


    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $searchDate = '';

    public $gameFilter = '';

    public $walletTypeFilter = '';
    public $walletFilter = '';

    public $statusFilter = '';
    public function getDepositsProperty()
    {
        $query = Deposit::with([
            'user',
            'game',
            'wallet.walletType',
            'wallet.walletAgent'
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

        if ($this->walletTypeFilter) {
            $query->whereHas('wallet', function ($q) {
                $q->where('wallet_type_id', $this->walletTypeFilter);
            });
        }

        if ($this->walletFilter) {

            $query->where(
                'wallet_id',
                $this->walletFilter
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

                    ->orWhereExists(function ($sub) use ($search) {

                        $sub->selectRaw(1)
                            ->from('game_accounts')
                            ->whereColumn(
                                'game_accounts.user_id',
                                'deposits.user_id'
                            )
                            ->whereColumn(
                                'game_accounts.game_id',
                                'deposits.game_id'
                            )
                            ->where(
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
            ->groupBy(fn ($d) => $d->created_at->format('Y-m-d'));

        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $perPage = 3;

        /**
         * STEP 1: paginate ONLY KEYS (dates)
         */
        $dates = $grouped->keys()->values();

        $paginatedDates = $dates->slice(
            ($currentPage - 1) * $perPage,
            $perPage
        )->values();

        /**
         * STEP 2: rebuild grouped collection safely
         */
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
    public function updatedWalletTypeFilter()
    {
        $this->walletFilter = null;
    }
    public function openModal($depositId)
    {
        $this->resetValidation();
       // $this->successMessage = null;

        $this->selectedDeposit = Deposit::findOrFail($depositId);

        // preload existing values
        $this->status = $this->selectedDeposit->status ?? 'pending';
        $this->admin_notes = $this->selectedDeposit->admin_notes;

        // clear inputs unless already handled
     //   $this->game_username = $this->selectedDeposit->game_username ?? '';
      //  $this->game_password = $this->selectedDeposit->game_password ?? '';

        $gameAccount = GameAccount::where('user_id', $this->selectedDeposit->user_id)
            ->where('game_id', $this->selectedDeposit->game_id)
            ->first();

        $this->game_username = $gameAccount->game_username ?? '';
        $this->game_password = $gameAccount->game_password ?? '';
        $this->game_points_loaded =
            $this->selectedDeposit->game_points_loaded;
    }

    public function closeModal()
    {
        $this->reset([
            'selectedDeposit',
            'status',
            'game_username',
            'game_password',
            'admin_notes',
            'game_points_loaded',
        ]);
    }

    public function processDeposit()
    {
        $deposit = Deposit::find($this->selectedDeposit->id);
        if (!$deposit->original_verified_by) {
            $deposit->original_verified_by = auth()->id();
        }
        $this->validate([
            'status' => 'required|in:pending,verified,rejected',
        ]);

        if ($this->status === 'verified') {

            $this->validate([
                'game_username' => 'required',
                'game_password' => 'required',
                'game_points_loaded' => 'required|numeric|min:0',
            ]);

        }

        $isFirstTime = is_null($deposit->original_verified_by);
        $bonus =
            $this->status === 'verified'
                ? ($this->game_points_loaded - $deposit->amount)
                : null;
        $deposit->update([
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,

            // ONLY set once
            'original_verified_by' => $deposit->original_verified_by,

            // keep your existing fields
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'game_points_loaded' =>
                $this->status === 'verified'
                    ? $this->game_points_loaded
                    : null,
            'bonus_points_added' =>
                $this->status === 'verified'
                    ? $bonus
                    : null,
        ]);

        // UPSERT game account
        if ($this->status === 'verified') {

            GameAccount::updateOrCreate(
                [
                    'user_id' => $deposit->user_id,
                    'game_id' => $deposit->game_id,
                ],
                [
                    'game_username' => $this->game_username,
                    'game_password' => $this->game_password,
                    'created_by' => auth()->id(),
                ]
            );

        }

        $admin = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | 1. NOTIFICATION FOR ADMIN / AGENT (internal log)
        |--------------------------------------------------------------------------
        */

        $adminsAgents = User::role(['admin', 'agent'])->get();

        foreach ($adminsAgents as $receiver) {

            Notification::create([
                'user_id' => $receiver->id,
                'type' => 'deposit_admin',

                'title' =>
                    'Deposit [' . $deposit->reference . '] processed',

                'message' =>
                    'Deposit of ' . $deposit->amount .
                    ' for player ' . $deposit->user->name .
                    ' (' . $deposit->user->playerProfile?->player_id . ')' .
                    ' for game ' . $deposit->game?->name .
                    ' has been ' . $this->status .
                    ' by ' . $admin->name,

                'action_text' => 'View Deposits',
                'action_url' => route('admin.deposits'),

                'is_read' => false,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. NOTIFICATION FOR PLAYER (future player system)
        |--------------------------------------------------------------------------
        */

        if ($this->status === 'verified') {

            Notification::create([
                'user_id' => $deposit->user_id,

                'type' => 'deposit_verified',

                'title' => 'Deposit Verified',

                'message' =>
                    'Your deposit [' .
                    $deposit->reference .
                    '] of $' .
                    $deposit->amount .
                    ' for ' .
                    $deposit->game->name .
                    ' is verified.' .

                    "\n\nGame Username: " . $this->game_username .
                    "\nGame Password: " . $this->game_password .
                    "\nPoints Loaded: " . $this->game_points_loaded.
                     "\nPlease click the Play Button to Play."  ,
                'action_text' => 'Play',

                'action_url' => str_starts_with(
                    $deposit->game?->game_url,
                    'http'
                )
                    ? $deposit->game?->game_url
                    : 'https://' . $deposit->game?->game_url,

                'is_read' => false,
            ]);

        }

        elseif ($this->status === 'rejected') {

            Notification::create([
                'user_id' => $deposit->user_id,

                'type' => 'deposit_rejected',

                'title' => 'Deposit Rejected',

                'message' =>
                    'Your deposit [' .
                    $deposit->reference .
                    '] of $' .
                    $deposit->amount .
                    ' for ' .
                    $deposit->game->name .
                    ' is rejected. Please wait for a while for our agent to re-verify.',

                'action_text' => 'Got It',

                'action_url' => route('player.notifications'),

                'is_read' => false,
            ]);

        }



        $this->closeModal();

        session()->flash('success', 'Deposit Processed Successfully!');

        $this->selectedDeposit = null;
        $this->reset(['status','game_username','game_password','admin_notes']);

        $this->dispatch('$refresh');

    }

    public function openProof($image)
    {
        $this->proofPreview = $image;
    }
    public function closeProof()
    {
        $this->proofPreview = null;
    }

    public function getGamesProperty()
    {
        return Game::orderBy('name')->get();
    }

    public function getWalletTypesProperty()
    {
        return WalletType::orderBy('name')->get();
    }

    public function getWalletsProperty()
    {
        return Wallet::when(
            $this->walletTypeFilter,
            fn ($q) => $q->where(
                'wallet_type_id',
                $this->walletTypeFilter
            )
        )
            ->orderBy('name')
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

    public function updatingGameFilter()
    {
        $this->resetPage();
    }

    public function updatingWalletTypeFilter()
    {
        $this->walletFilter = '';
        $this->resetPage();
    }

    public function updatingWalletFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.deposits')
            ->layout('layouts.private');
    }
}
