<?php

namespace App\Livewire\Admin;

use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\SpinRewardEntitlement;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletType;
use App\Services\Spin\SpinBonusService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

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

    public int $pendingPromotionalBonus = 0;

    public ?array $activeVipBadge = null;

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
            'wallet.walletAgent',
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
                $date => $grouped[$date],
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

    public function openModal($depositId, SpinBonusService $bonusService)
    {
        $this->resetValidation();
        // $this->successMessage = null;

        $this->selectedDeposit = Deposit::findOrFail($depositId);
        $this->pendingPromotionalBonus = $bonusService->pending($this->selectedDeposit->user);
        $badge = SpinRewardEntitlement::where('user_id', $this->selectedDeposit->user_id)
            ->where('entitlement_type', 'badge')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()->first();
        $this->activeVipBadge = $badge ? [
            'name' => $badge->metadata['label'] ?? 'VIP Badge',
            'expires' => $badge->expires_at?->format('F j, Y g:i A'),
        ] : null;

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
            'pendingPromotionalBonus',
            'activeVipBadge',
        ]);
    }

    public function fulfillPromotionalBonus(int $depositId, SpinBonusService $bonusService): void
    {
        $this->resetValidation('bonus');
        $deposit = Deposit::with('user')->findOrFail($depositId);
        $amount = $bonusService->fulfill(auth()->user(), $deposit);
        $this->pendingPromotionalBonus = $bonusService->pending($deposit->user);
        session()->flash('success', "{$amount} promotional Bonus Points fulfilled.");
    }

    public function processDeposit()
    {
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
        $result = DB::transaction(function () {
            $deposit = Deposit::whereKey($this->selectedDeposit->id)->lockForUpdate()->firstOrFail();
            $wasVerified = $deposit->status === 'verified';
            $originalVerifier = $deposit->original_verified_by ?: auth()->id();
            $bonus = $this->status === 'verified'
                ? ($this->game_points_loaded - $deposit->amount)
                : null;

            $deposit->update([
                'status' => $this->status,
                'admin_notes' => $this->admin_notes,
                'original_verified_by' => $originalVerifier,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'game_points_loaded' => $this->status === 'verified' ? $this->game_points_loaded : null,
                'bonus_points_added' => $this->status === 'verified' ? $bonus : null,
            ]);

            if ($this->status === 'verified') {
                GameAccount::updateOrCreate(
                    ['user_id' => $deposit->user_id, 'game_id' => $deposit->game_id],
                    [
                        'game_username' => $this->game_username,
                        'game_password' => $this->game_password,
                        'created_by' => auth()->id(),
                    ]
                );
            }

            $deposit = $deposit->fresh(['user.playerProfile', 'game']);
            $promotionalBonus = $this->status === 'verified' && ! $wasVerified
                ? app(SpinBonusService::class)->consumeForVerification(auth()->user(), $deposit)
                : 0;

            return ['deposit' => $deposit, 'promotional_bonus' => $promotionalBonus];
        });

        $deposit = $result['deposit'];
        $promotionalBonus = (int) $result['promotional_bonus'];

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

                'title' => 'Deposit ['.$deposit->reference.'] processed',

                'message' => 'Deposit of '.$deposit->amount.
                    ' for player '.$deposit->user->name.
                    ' ('.$deposit->user->playerProfile?->player_id.')'.
                    ' for game '.$deposit->game?->name.
                    ' has been '.$this->status.
                    ' by '.$admin->name
                    .($this->status === 'verified'
                        ? "\nDeposit Amount: ".number_format((float) $deposit->amount, 2)
                            ."\nPending Bonus Points: ".number_format($promotionalBonus)
                            ."\nTotal Promotional Reference: ".number_format((float) $deposit->amount + $promotionalBonus, 2)
                        : ''),

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

                'message' => 'Your deposit ['.
                    $deposit->reference.
                    '] of $'.
                    $deposit->amount.
                    ' for '.
                    $deposit->game->name.
                    ' is verified.'.

                    "\n\nDeposit Amount: ".number_format((float) $deposit->amount, 2).
                    "\nPending Bonus Points: ".number_format($promotionalBonus).
                    "\nTotal Promotional Reference: ".number_format((float) $deposit->amount + $promotionalBonus, 2).
                    "\nGame Username: ".$this->game_username.
                    "\nGame Password: ".$this->game_password.
                    "\nPoints Loaded: ".$this->game_points_loaded.
                     "\nPlease click the Play Button to Play.",
                'action_text' => 'Play',

                'action_url' => str_starts_with(
                    $deposit->game?->game_url,
                    'http'
                )
                    ? $deposit->game?->game_url
                    : 'https://'.$deposit->game?->game_url,

                'is_read' => false,
            ]);

        } elseif ($this->status === 'rejected') {

            Notification::create([
                'user_id' => $deposit->user_id,

                'type' => 'deposit_rejected',

                'title' => 'Deposit Rejected',

                'message' => 'Your deposit ['.
                    $deposit->reference.
                    '] of $'.
                    $deposit->amount.
                    ' for '.
                    $deposit->game->name.
                    ' is rejected. Please wait for a while for our agent to re-verify.',

                'action_text' => 'Got It',

                'action_url' => route('player.notifications'),

                'is_read' => false,
            ]);

        }

        $this->closeModal();

        session()->flash('success', 'Deposit Processed Successfully!');

        $this->selectedDeposit = null;
        $this->reset(['status', 'game_username', 'game_password', 'admin_notes']);

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
