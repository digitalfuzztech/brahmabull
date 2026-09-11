<?php

namespace App\Livewire\Admin;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Notifications extends Component
{
    use WithPagination;

    private const TYPE_GROUPS = [
        'deposit' => ['deposit_created', 'deposit_admin', 'deposit_submitted', 'deposit_verified', 'deposit_rejected'],
        'cashout' => ['cashout_created', 'cashout_admin', 'cashout_submitted', 'cashout_paid', 'cashout_rejected'],
        'brahma_deposit' => ['brahma_deposit_created', 'brahma_deposit_submitted', 'brahma_deposit_verified', 'brahma_deposit_rejected', 'brahma_deposit_rejected_admin', 'brahma_balance_loaded', 'brahma_balance_adjusted'],
        'brahma_play' => ['brahma_play_created', 'brahma_play_submitted', 'brahma_play_verified', 'brahma_play_verified_admin', 'brahma_play_rejected', 'brahma_play_rejected_admin'],
    ];

    public $search = '';

    public $type = '';

    public $game_id = '';

    public $readStatus = '';

    protected $listeners = [
        'refreshNotifications' => '$refresh',
    ];

    public function getNotificationsProperty()
    {
        $search = trim($this->search);

        return Notification::query()

            ->where('user_id', Auth::id())

            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $like = '%'.$search.'%';

                    $sub->where('title', 'like', $like)
                        ->orWhere('message', 'like', $like)
                        ->orWhere('action_text', 'like', $like)
                        ->orWhere('data', 'like', $like);
                });
            })

            ->when($this->type, function ($q) {
                $q->whereIn('type', self::TYPE_GROUPS[$this->type] ?? [$this->type]);
            })
            ->when($this->readStatus !== '', function ($q) {

                $q->where(
                    'is_read',
                    $this->readStatus
                );

            })
            ->latest()
            ->paginate(30);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedReadStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.notifications', [
            'notifications' => $this->notifications,
        ])->layout('layouts.private');
    }

    public function toggleRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $notification) {
            return;
        }

        $notification->update([
            'is_read' => ! $notification->is_read,
            'read_at' => $notification->is_read ? null : now(),
        ]);
        $this->dispatch('refreshBell');
    }

    public function markAndRedirect($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $notification) {
            return;
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->dispatch('refreshBell');

        if ($notification->type === 'game') {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            if (auth()->user()->hasRole('admin')) {
                return redirect('/admin/catalog');
            }

            return redirect('/agent/catalog');
        }

        if ($notification->type === 'wallet') {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            if (auth()->user()->hasRole('admin')) {
                return redirect('/admin/accounts');
            }

            return redirect('/agent/accounts');
        }

        if ($notification->type === 'deposit_created') {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            if (auth()->user()->hasRole('admin')) {
                return redirect('/admin/funding');
            }

            return redirect('/agent/funding');
        }
        if ($notification->type === 'cashout_created') {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            if (auth()->user()->hasRole('admin')) {
                return redirect('/admin/payout');
            }

            return redirect('/agent/payout');
        }
        if ($notification->type === 'cashout_admin') {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            if (auth()->user()->hasRole('admin')) {
                return redirect('/admin/payout');
            }

            return redirect('/agent/payout');
        }

        if (in_array($notification->type, [
            'brahma_deposit_created',
            'brahma_deposit_verified',
            'brahma_deposit_rejected_admin',
            'brahma_play_created',
            'brahma_play_verified_admin',
            'brahma_play_rejected_admin',
        ], true)) {

            return redirect($notification->action_url);
        }

        if (in_array($notification->type, [
            'chat_human_support_requested',
            'chat_support_reminder',
            'chat_conversation_assigned',
            'chat_team_direct_message',
            'chat_team_group_added',
        ], true) && $notification->action_url) {
            return redirect($notification->action_url);
        }
        // redirect based on type
        //  if ($notification->type === 'deposit_created') {
        //       return redirect()->route('admin.deposits');
        //   }

        //   if ($notification->type === 'cashout_created') {
        //        return redirect()->route('admin.cashouts');
        //   }

        return redirect()->route('admin.notifications');
    }
}
