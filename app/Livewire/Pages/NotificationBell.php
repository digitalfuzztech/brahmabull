<?php

namespace App\Livewire\Pages;

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
    public function render()
    {
        return view(
            'livewire.pages.notification-bell'
        );
    }
}
