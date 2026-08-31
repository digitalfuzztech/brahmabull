<?php

namespace App\Livewire\Admin;

use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BrahmaDeposits extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $selectedDeposit = null;
    public $status = 'pending';
    public $load_balance;
    public $admin_notes;
    public $proofPreview = null;

    public $search = '';
    public $searchDate = '';
    public $walletTypeFilter = '';
    public $walletFilter = '';
    public $statusFilter = '';

    public function getDepositsProperty()
    {
        $query = BrahmaDeposit::with(['user.playerProfile', 'wallet.walletType', 'wallet.walletAgent', 'processor', 'verifier']);

        if ($this->searchDate) {
            $query->whereDate('created_at', $this->searchDate);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->walletTypeFilter) {
            $query->whereHas('wallet', fn ($q) => $q->where('wallet_type_id', $this->walletTypeFilter));
        }

        if ($this->walletFilter) {
            $query->where('wallet_id', $this->walletFilter);
        }

        if ($this->search) {
            $search = $this->search;

            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user.playerProfile', fn ($p) => $p->where('player_id', 'like', "%{$search}%"));
            });
        }

        $grouped = $query->latest()->get()->groupBy(fn ($d) => $d->created_at->format('Y-m-d'));
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 3;
        $dates = $grouped->keys()->values();
        $paginatedDates = $dates->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $filtered = collect($paginatedDates)->mapWithKeys(fn ($date) => [$date => $grouped[$date]]);

        return new LengthAwarePaginator($filtered, $grouped->count(), $perPage, $currentPage, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function openModal($depositId): void
    {
        $this->resetValidation();

        $this->selectedDeposit = BrahmaDeposit::with(['user.playerProfile', 'wallet.walletType', 'wallet.walletAgent', 'processor'])->findOrFail($depositId);
        $this->status = $this->selectedDeposit->status ?? 'pending';
        $this->load_balance = $this->selectedDeposit->load_balance;
        $this->admin_notes = $this->selectedDeposit->admin_notes;
    }

    public function closeModal(): void
    {
        $this->reset(['selectedDeposit', 'status', 'load_balance', 'admin_notes']);
    }

    public function processDeposit(): void
    {
        $this->validate([
            'status' => 'required|in:pending,verified,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        if ($this->status === 'verified') {
            $this->validate([
                'load_balance' => 'required|numeric|min:0.01|max:9999999999.99',
            ]);
        }

        $result = DB::transaction(function () {
            $deposit = BrahmaDeposit::whereKey($this->selectedDeposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->credited_at || BrahmaBalanceTransaction::where('source_type', BrahmaDeposit::class)->where('source_id', $deposit->id)->where('type', 'credit')->exists()) {
                return ['deposit' => $deposit->fresh(['user']), 'credited' => false, 'already_processed' => true];
            }

            if ($this->status === 'verified') {
                $player = User::whereKey($deposit->user_id)->lockForUpdate()->firstOrFail();
                $before = (float) $player->brahma_balance;
                $amount = (float) $this->load_balance;
                $after = $before + $amount;

                $player->forceFill(['brahma_balance' => $after])->save();

                $deposit->update([
                    'status' => 'verified',
                    'load_balance' => $amount,
                    'balance_before_credit' => $before,
                    'balance_after_credit' => $after,
                    'processed_by' => auth()->id(),
                    'verified_by' => auth()->id(),
                    'processed_at' => now(),
                    'verified_at' => now(),
                    'credited_at' => now(),
                    'admin_notes' => $this->admin_notes,
                ]);

                BrahmaBalanceTransaction::create([
                    'user_id' => $player->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'source_type' => BrahmaDeposit::class,
                    'source_id' => $deposit->id,
                    'performed_by' => auth()->id(),
                    'description' => 'Brahma Balance deposit verified: ' . $deposit->reference,
                ]);

                return ['deposit' => $deposit->fresh(['user']), 'credited' => true, 'already_processed' => false];
            }

            $deposit->update([
                'status' => $this->status,
                'load_balance' => null,
                'processed_by' => $this->status === 'pending' ? null : auth()->id(),
                'verified_by' => null,
                'processed_at' => $this->status === 'pending' ? null : now(),
                'verified_at' => null,
                'admin_notes' => $this->admin_notes,
            ]);

            return ['deposit' => $deposit->fresh(['user']), 'credited' => false, 'already_processed' => false];
        });

        $deposit = $result['deposit'];
        $processor = auth()->user();

        if ($result['already_processed']) {
            session()->flash('success', 'This Brahma Deposit was already financially processed. No balance was changed.');
        } elseif ($this->status === 'verified') {
            Notification::create([
                'user_id' => $deposit->user_id,
                'type' => 'brahma_balance_loaded',
                'title' => 'Brahma Balance Loaded',
                'message' => 'Your Brahma Balance has been loaded with $' . number_format((float) $deposit->load_balance, 2) . '. Your current Brahma Balance is $' . number_format((float) $deposit->balance_after_credit, 2) . '. Reference: ' . $deposit->reference . '.',
                'action_text' => 'Got It',
                'action_url' => route('player.notifications'),
                'entity_type' => BrahmaDeposit::class,
                'entity_id' => $deposit->id,
                'created_by' => auth()->id(),
            ]);

            $this->notifyAdminsAgentsProcessed($deposit, $processor, 'brahma_deposit_verified', 'Brahma Balance Loaded', 'Brahma Balance of $' . number_format((float) $deposit->load_balance, 2) . ' was loaded to ' . $deposit->user->name . ' (' . $deposit->user->username . ') by ' . $processor->name . ' on ' . now()->format('Y-m-d H:i:s') . '. New balance: $' . number_format((float) $deposit->balance_after_credit, 2) . '.');
            session()->flash('success', 'Brahma Deposit verified and balance loaded.');
        } elseif ($this->status === 'rejected') {
            Notification::create([
                'user_id' => $deposit->user_id,
                'type' => 'brahma_deposit_rejected',
                'title' => 'Brahma Balance Deposit Rejected',
                'message' => 'Your Brahma Balance deposit [' . $deposit->reference . '] of $' . number_format((float) $deposit->amount, 2) . ' was rejected.',
                'action_text' => 'Got It',
                'action_url' => route('player.notifications'),
                'entity_type' => BrahmaDeposit::class,
                'entity_id' => $deposit->id,
                'created_by' => auth()->id(),
            ]);

            $this->notifyAdminsAgentsProcessed($deposit, $processor, 'brahma_deposit_rejected_admin', 'Brahma Deposit Rejected', 'Brahma Balance deposit [' . $deposit->reference . '] for ' . $deposit->user->name . ' (' . $deposit->user->username . ') was rejected by ' . $processor->name . '.');
            session()->flash('success', 'Brahma Deposit rejected.');
        } else {
            session()->flash('success', 'Brahma Deposit updated.');
        }

        $this->closeModal();
        $this->dispatch('$refresh');
    }

    private function notifyAdminsAgentsProcessed(BrahmaDeposit $deposit, User $processor, string $type, string $title, string $message): void
    {
        foreach (User::role(['admin', 'agent'])->get() as $receiver) {
            Notification::create([
                'user_id' => $receiver->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_text' => 'View Brahma Deposits',
                'action_url' => $receiver->hasRole('admin') ? route('admin.brahma.deposits') : route('agent.brahma.deposits'),
                'entity_type' => BrahmaDeposit::class,
                'entity_id' => $deposit->id,
                'created_by' => $processor->id,
            ]);
        }
    }

    public function openProof($image): void
    {
        $this->proofPreview = $image;
    }

    public function closeProof(): void
    {
        $this->proofPreview = null;
    }

    public function getWalletTypesProperty()
    {
        return WalletType::orderBy('name')->get();
    }

    public function getWalletsProperty()
    {
        return Wallet::when($this->walletTypeFilter, fn ($q) => $q->where('wallet_type_id', $this->walletTypeFilter))
            ->orderBy('name')
            ->get();
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'searchDate', 'walletTypeFilter', 'walletFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function updatedWalletTypeFilter(): void
    {
        $this->walletFilter = '';
    }

    public function render()
    {
        return view('livewire.admin.brahma-deposits')->layout('layouts.private');
    }
}
