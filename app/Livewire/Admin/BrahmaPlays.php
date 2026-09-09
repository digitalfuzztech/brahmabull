<?php

namespace App\Livewire\Admin;

use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaPlayRequest;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use App\Services\Spin\SpinBonusService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class BrahmaPlays extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $selectedPlay = null;

    public $status = 'pending';

    public $game_username = '';

    public $game_password = '';

    public $rejection_note = '';

    public int $pendingPromotionalBonus = 0;

    public $search = '';

    public $searchDate = '';

    public $gameFilter = '';

    public $statusFilter = '';

    public function getPlaysProperty()
    {
        $query = BrahmaPlayRequest::with(['user.playerProfile', 'game', 'processor', 'verifier']);

        if ($this->searchDate) {
            $query->whereDate('created_at', $this->searchDate);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->gameFilter) {
            $query->where('game_id', $this->gameFilter);
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('game_username', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $grouped = $query->latest()->get()->groupBy(fn ($p) => $p->created_at->format('Y-m-d'));
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

    public function openModal($playId, SpinBonusService $bonusService): void
    {
        $this->resetValidation();

        $this->selectedPlay = BrahmaPlayRequest::with(['user.playerProfile', 'game'])->findOrFail($playId);
        $this->pendingPromotionalBonus = $bonusService->pending($this->selectedPlay->user);
        $this->status = $this->selectedPlay->status ?? 'pending';
        $this->game_username = $this->selectedPlay->game_username ?? '';
        $this->game_password = $this->selectedPlay->game_password ?? '';
        $this->rejection_note = $this->selectedPlay->rejection_note ?? '';

        $account = GameAccount::where('user_id', $this->selectedPlay->user_id)
            ->where('game_id', $this->selectedPlay->game_id)
            ->first();

        if ($account && ! $this->game_username) {
            $this->game_username = $account->game_username;
            $this->game_password = $account->game_password;
        }
    }

    public function closeModal(): void
    {
        $this->reset(['selectedPlay', 'status', 'game_username', 'game_password', 'rejection_note', 'pendingPromotionalBonus']);
    }

    public function fulfillPromotionalBonus(int $playId, SpinBonusService $bonusService): void
    {
        $this->resetValidation('bonus');
        $play = BrahmaPlayRequest::with('user')->findOrFail($playId);
        $amount = $bonusService->fulfill(auth()->user(), $play);
        $this->pendingPromotionalBonus = $bonusService->pending($play->user);
        session()->flash('success', "{$amount} promotional Bonus Points fulfilled.");
    }

    public function processPlay(): void
    {
        $this->processPlayRequest(false);
    }

    public function adminProcessPlay(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $this->processPlayRequest(true);
    }

    private function processPlayRequest(bool $allowAdminEdits): void
    {
        $this->validate([
            'status' => 'required|in:pending,verified,rejected',
        ]);

        if ($this->status === 'verified') {
            $this->validate([
                'game_username' => 'required|string',
                'game_password' => 'required|string',
            ]);
        }

        if ($this->status === 'rejected') {
            $this->validate([
                'rejection_note' => 'required|string|min:3',
            ]);
        }

        $result = DB::transaction(function () use ($allowAdminEdits) {
            $play = BrahmaPlayRequest::whereKey($this->selectedPlay->id)->lockForUpdate()->firstOrFail();

            if ($play->debited_at || BrahmaBalanceTransaction::where('source_type', BrahmaPlayRequest::class)->where('source_id', $play->id)->where('type', 'debit')->exists()) {
                if ($allowAdminEdits) {
                    if ($this->status !== $play->status) {
                        throw ValidationException::withMessages([
                            'status' => 'A debited play cannot change financial status.',
                        ]);
                    }

                    $play->update([
                        'game_username' => $this->game_username,
                        'game_password' => $this->game_password,
                    ]);

                    GameAccount::updateOrCreate(
                        [
                            'user_id' => $play->user_id,
                            'game_id' => $play->game_id,
                        ],
                        [
                            'game_username' => $this->game_username,
                            'game_password' => $this->game_password,
                            'created_by' => auth()->id(),
                        ]
                    );

                    return ['play' => $play->fresh(['user', 'game']), 'debited' => false, 'already_processed' => true, 'credentials_corrected' => true];
                }

                return ['play' => $play->fresh(['user', 'game']), 'debited' => false, 'already_processed' => true];
            }

            if ($this->status === 'verified') {
                $player = User::whereKey($play->user_id)->lockForUpdate()->firstOrFail();
                $before = (float) $player->brahma_balance;
                $amount = (float) $play->points_to_load;

                if ($before < $amount) {
                    return ['play' => $play->fresh(['user', 'game']), 'debited' => false, 'insufficient' => true];
                }

                $after = $before - $amount;

                if ($after < 0) {
                    return ['play' => $play->fresh(['user', 'game']), 'debited' => false, 'insufficient' => true];
                }

                $player->forceFill(['brahma_balance' => $after])->save();

                $play->update([
                    'status' => 'verified',
                    'game_username' => $this->game_username,
                    'game_password' => $this->game_password,
                    'processed_by' => auth()->id(),
                    'verified_by' => auth()->id(),
                    'processed_at' => now(),
                    'verified_at' => now(),
                    'debited_at' => now(),
                    'balance_before_debit' => $before,
                    'balance_after_debit' => $after,
                    'rejection_note' => null,
                ]);

                BrahmaBalanceTransaction::create([
                    'user_id' => $player->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'source_type' => BrahmaPlayRequest::class,
                    'source_id' => $play->id,
                    'performed_by' => auth()->id(),
                    'description' => 'Brahma Play request verified: '.$play->reference,
                ]);

                GameAccount::updateOrCreate(
                    [
                        'user_id' => $play->user_id,
                        'game_id' => $play->game_id,
                    ],
                    [
                        'game_username' => $this->game_username,
                        'game_password' => $this->game_password,
                        'created_by' => auth()->id(),
                    ]
                );

                $play = $play->fresh(['user', 'game']);
                $bonusAwarded = app(SpinBonusService::class)->consumeForVerification(auth()->user(), $play);

                return [
                    'play' => $play,
                    'debited' => true,
                    'already_processed' => false,
                    'bonus_awarded' => $bonusAwarded,
                ];
            }

            $play->update([
                'status' => $this->status,
                'processed_by' => $this->status === 'pending' ? null : auth()->id(),
                'verified_by' => null,
                'processed_at' => $this->status === 'pending' ? null : now(),
                'verified_at' => null,
                'rejection_note' => $this->status === 'rejected' ? $this->rejection_note : null,
            ]);

            return ['play' => $play->fresh(['user', 'game']), 'debited' => false, 'already_processed' => false];
        });

        if (($result['insufficient'] ?? false) === true) {
            $this->addError('status', 'Player no longer has sufficient Brahma Balance to verify this request.');

            return;
        }

        $play = $result['play'];
        $processor = auth()->user();

        if (($result['credentials_corrected'] ?? false) === true) {
            session()->flash('success', 'Game credentials updated. No additional Brahma Balance was debited.');
        } elseif ($result['already_processed']) {
            session()->flash('success', 'This Brahma Play was already financially processed. No balance was changed.');
        } elseif ($this->status === 'verified') {
            $url = $play->game?->game_url;
            $playUrl = $url && str_starts_with($url, 'http') ? $url : ($url ? 'https://'.$url : route('games'));
            $requestedPoints = (float) $play->points_to_load;
            $bonusAwarded = (int) ($result['bonus_awarded'] ?? 0);
            $totalPointsLoaded = $requestedPoints + $bonusAwarded;

            Notification::create([
                'user_id' => $play->user_id,
                'type' => 'brahma_play_verified',
                'title' => 'Play request verified',
                'message' => 'Your Brahma Play request ['.$play->reference.'] for '.$play->game?->name.' was verified.'
                    ."\n\nRequested Points: ".number_format($requestedPoints, 2)
                    ."\nPending Bonus Points: ".number_format($bonusAwarded)
                    ."\nTotal Points Loaded: ".number_format($totalPointsLoaded, 2)
                    ."\nGame Username: ".$play->game_username
                    ."\nGame Password: ".$play->game_password
                    ."\nPlease click the Play Button to Play.",
                'action_text' => 'Play',
                'action_url' => $playUrl,
                'entity_type' => BrahmaPlayRequest::class,
                'entity_id' => $play->id,
                'game_id' => $play->game_id,
                'created_by' => auth()->id(),
            ]);

            $this->notifyAdminsAgentsProcessed(
                $play,
                $processor,
                'brahma_play_verified_admin',
                'Brahma Play Verified',
                'Player: '.$play->user->name.' ('.$play->user->username.")\nRequested Points: ".number_format($requestedPoints, 2)
                    ."\nPending Bonus Points: ".number_format($bonusAwarded)
                    ."\nTotal Points Loaded: ".number_format($totalPointsLoaded, 2)
                    ."\nVerified by: ".$processor->name
                    ."\nRemaining balance: $".number_format((float) $play->balance_after_debit, 2)
            );
            session()->flash('success', 'Brahma Play verified and balance debited.');
        } elseif ($this->status === 'rejected') {
            Notification::create([
                'user_id' => $play->user_id,
                'type' => 'brahma_play_rejected',
                'title' => 'Brahma Play Request Rejected',
                'message' => 'Your Brahma Play request ['.$play->reference.'] for '.$play->game?->name.' was rejected. Reason: '.$play->rejection_note,
                'action_text' => 'Got It',
                'action_url' => route('player.notifications'),
                'entity_type' => BrahmaPlayRequest::class,
                'entity_id' => $play->id,
                'game_id' => $play->game_id,
                'created_by' => auth()->id(),
            ]);

            $this->notifyAdminsAgentsProcessed($play, $processor, 'brahma_play_rejected_admin', 'Brahma Play Rejected', 'Brahma Play ['.$play->reference.'] for '.$play->user->name.' ('.$play->user->username.') game '.$play->game?->name.' was rejected by '.$processor->name.'. Reason: '.$play->rejection_note);
            session()->flash('success', 'Brahma Play rejected.');
        } else {
            session()->flash('success', 'Brahma Play updated.');
        }

        $this->closeModal();
        $this->dispatch('$refresh');
    }

    private function notifyAdminsAgentsProcessed(BrahmaPlayRequest $play, User $processor, string $type, string $title, string $message): void
    {
        foreach (User::role(['admin', 'agent'])->get() as $receiver) {
            Notification::create([
                'user_id' => $receiver->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_text' => 'View Brahma Plays',
                'action_url' => $receiver->hasRole('admin') ? route('admin.brahma.plays') : route('agent.brahma.plays'),
                'entity_type' => BrahmaPlayRequest::class,
                'entity_id' => $play->id,
                'game_id' => $play->game_id,
                'created_by' => $processor->id,
            ]);
        }
    }

    public function getGamesProperty()
    {
        return Game::orderBy('name')->get();
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'searchDate', 'gameFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.admin.brahma-plays')->layout('layouts.private');
    }
}
