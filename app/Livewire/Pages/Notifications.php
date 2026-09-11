<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Notification;

class Notifications extends Component
{
    use WithPagination;

    private const TYPE_GROUPS = [
        'deposit' => ['deposit_created', 'deposit_admin', 'deposit_submitted', 'deposit_verified', 'deposit_rejected'],
        'cashout' => ['cashout_created', 'cashout_admin', 'cashout_submitted', 'cashout_paid', 'cashout_rejected'],
        'game' => ['game'],
        'brahma' => [
            'brahma_deposit_created', 'brahma_deposit_submitted', 'brahma_deposit_verified',
            'brahma_deposit_rejected', 'brahma_deposit_rejected_admin', 'brahma_balance_loaded',
            'brahma_balance_adjusted', 'brahma_play_created', 'brahma_play_submitted',
            'brahma_play_verified', 'brahma_play_verified_admin', 'brahma_play_rejected',
            'brahma_play_rejected_admin',
        ],
    ];

    public $search = '';

    public $type = '';
    public $readStatus = '';
    public $previewImage = null;
    protected $listeners = [
        'refreshNotifications' => '$refresh'
    ];

    public function getNotificationsProperty()
    {
        $search = trim($this->search);

        return Notification::query()

            ->where('user_id', auth()->id())

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

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedType()
    {
        $this->resetPage();
    }

    public function updatedReadStatus()
    {
        $this->resetPage();
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

    public function acknowledge($id)
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
