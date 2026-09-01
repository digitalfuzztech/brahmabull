<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Notification;

class Notifications extends Component
{
    use WithPagination;

    public $search = '';

    public $type = '';
    public $readStatus = '';
    public $previewImage = null;
    protected $listeners = [
        'refreshNotifications' => '$refresh'
    ];

    public function getNotificationsProperty()
    {
        return Notification::query()

            ->where('user_id', auth()->id())

            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('message', 'like', '%' . $this->search . '%');
                });
            })

            ->when($this->type, function ($q) {

                if ($this->type === 'deposit') {

                    $q->whereIn('type', [
                        'deposit_submitted',
                        'deposit_verified',
                        'deposit_rejected',
                    ]);

                } elseif ($this->type === 'cashout') {

                    $q->whereIn('type', [
                        'cashout_submitted',
                        'cashout_paid',
                        'cashout_rejected',
                    ]);

                } elseif ($this->type === 'game') {

                    $q->where('type', 'game');

                } elseif ($this->type === 'brahma') {

                    $q->whereIn('type', [
                        'brahma_deposit_submitted',
                        'brahma_balance_loaded',
                        'brahma_balance_adjusted',
                        'brahma_deposit_rejected',
                        'brahma_play_submitted',
                        'brahma_play_verified',
                        'brahma_play_rejected',
                    ]);

                }

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

    public function toggleRead($id)
    {
        $notification = Notification::where(
            'id',
            $id
        )
            ->where(
                'user_id',
                auth()->id()
            )
            ->first();

        if (!$notification) {
            return;
        }

        $notification->update([
            'is_read' => !$notification->is_read,
        ]);

        $this->dispatch('refreshBell');
    }

    public function markAndRedirect($id)
    {
        return $this->openNotification($id);
    }

    public function openNotification($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$notification) {
            return;
        }

        if (!$notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        $this->dispatch('refreshBell');

        $url = trim((string) $notification->action_url);

        if ($url === '') {
            return;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);
        $isInternal = (str_starts_with($url, '/') && !str_starts_with($url, '//'))
            || ($targetHost !== null && $targetHost === $appHost);

        $this->redirect($url, navigate: $isInternal);
    }
    public function markPlayRead($id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id != auth()->id()) {
            abort(403);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function viewProof($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$notification) {
            return;
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        $data = $notification->data;

        $this->previewImage = $data['payment_proof'] ?? null;

        $this->dispatch('refreshBell');
    }
    public function closePreview()
    {
        $this->previewImage = null;
    }
    public function render()
    {
        return view(
            'livewire.pages.notifications',
            [
                'notifications' => $this->notifications,
            ]
        )->layout('layouts.public');
    }
}
