<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationBell extends Component
{
    public bool $open = false;
    protected $listeners = [
        'refreshBell' => '$refresh'
    ];
    public function toggle()
    {
        $this->open = !$this->open;
    }

    public function getUnreadCountProperty()
    {
        return Notification::query()

            ->where(
                'user_id',
                Auth::id()
            )

            ->where(
                'is_read',
                false
            )

            ->count();
    }
    public function getUnreadNotificationsProperty()
    {
        return Notification::where(
            'user_id',
            Auth::id()
        )
            ->where(
                'is_read',
                false
            )
            ->latest()
            ->take(20)
            ->get();
    }
    public function openNotification($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$notification) {
            return;
        }


        if ($notification->action_url) {

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            $this->dispatch('refreshBell');
        }


        $url = $notification->action_url;


        /*
        |--------------------------------------------------------------------------
        | Fix admin/agent routes
        |--------------------------------------------------------------------------
        */

        if (
            auth()->user()->hasRole('agent')
            &&
            str_contains($url, 'admin.')
        ) {

            $url = str_replace(
                'admin.',
                'agent.',
                $url
            );

        }


        if (
            auth()->user()->hasRole('agent')
            &&
            str_contains($url, '/admin/')
        ) {

            $url = str_replace(
                '/admin/',
                '/agent/',
                $url
            );

        }


        if (!$url) {

            $url = auth()->user()->hasRole('admin')
                ? route('admin.notifications')
                : route('agent.notifications');

        }


        return redirect($url);
    }
    public function render()
    {
        return view(
            'livewire.admin.notification-bell'
        );
    }
}
