<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class Notifications extends Component
{
    use WithPagination;

    public $search = '';
    public $type = '';
    public $game_id = '';
    public $readStatus = '';
    protected $listeners = [
        'refreshNotifications' => '$refresh'
    ];



    public function getNotificationsProperty()
    {
        return Notification::query()

            ->where('user_id', Auth::id())

            ->when($this->search, function ($q) {
                $q->where('message', 'like', '%' . $this->search . '%');
            })

            ->when($this->type, function ($q) {
                $q->where('type', $this->type);
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

        if (!$notification) return;

        $notification->update([
            'is_read' => !$notification->is_read,
            'read_at' => $notification->is_read ? null : now(),
        ]);
        $this->dispatch('refreshBell');
    }
    public function markAndRedirect($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$notification) return;

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
                return redirect('/admin/games');
            }

            return redirect('/agent/games');
        }

        if ($notification->type === 'wallet') {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            if (auth()->user()->hasRole('admin')) {
                return redirect('/admin/wallets');
            }

            return redirect('/agent/wallets');
        }



        // redirect based on type
        if ($notification->type === 'deposit_created') {
            return redirect()->route('admin.deposits');
        }

        if ($notification->type === 'cashout_created') {
            return redirect()->route('admin.cashouts');
        }

        return redirect()->route('admin.notifications');
    }
}
