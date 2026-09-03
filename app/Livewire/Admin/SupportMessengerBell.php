<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Chat\SupportMessengerOverviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\On;
use Livewire\Component;

class SupportMessengerBell extends Component
{
    public int $unreadCount = 0;

    public array $recent = [];

    public function mount(SupportMessengerOverviewService $overview): void
    {
        $this->unreadCount = $overview->unreadCount($this->staff());
        $this->recent = $overview->recent($this->staff())->all();
    }

    public function refreshSupportMessenger(SupportMessengerOverviewService $overview): void
    {
        $this->unreadCount = $overview->unreadCount($this->staff());
        $this->recent = $overview->recent($this->staff())->all();
    }

    #[On('support-unread-refresh')]
    public function refreshUnread(SupportMessengerOverviewService $overview): void
    {
        $this->refreshSupportMessenger($overview);
    }

    public function openConversation(int $conversationId): void
    {
        $route = $this->staff()->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
        $this->redirect(route($route, ['domain' => 'support', 'conversation' => $conversationId]), navigate: true);
    }

    public function goToSupportInbox(): void
    {
        $route = $this->staff()->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
        $this->redirect(route($route, ['domain' => 'support']), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.support-messenger-bell');
    }

    private function staff(): User
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may use Support Messenger.');
        }

        return $user;
    }
}
