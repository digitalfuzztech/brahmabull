<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatPresenceService;
use App\Services\Chat\MessengerOverviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class MessengerBell extends Component
{
    public bool $open = false;

    public int $unreadCount = 0;

    public array $recent = [];

    public function mount(
        ChatPresenceService $presence,
        MessengerOverviewService $overview,
        BrahmaNoticeboardService $noticeboard,
    ): void {
        $presence->heartbeat($this->staff());
        $noticeboard->ensureAndSyncParticipants();
        $this->unreadCount = $overview->unreadCount($this->staff());
    }

    public function refreshMessenger(ChatPresenceService $presence, MessengerOverviewService $overview): void
    {
        $presence->heartbeat($this->staff());
        $this->unreadCount = $overview->unreadCount($this->staff());

        if ($this->open) {
            $this->recent = $overview->recent($this->staff())->all();
        }
    }

    public function toggle(MessengerOverviewService $overview): void
    {
        $this->open = ! $this->open;
        $this->recent = $this->open ? $overview->recent($this->staff())->all() : [];
    }

    public function openConversation(string $domain, int $conversationId): void
    {
        if (! in_array($domain, ['support', 'team'], true)) {
            throw new AuthorizationException('Unknown Messenger domain.');
        }

        $route = $this->staff()->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
        $this->redirect(route($route, ['domain' => $domain, 'conversation' => $conversationId]), navigate: true);
    }

    public function goToInbox(): void
    {
        $route = $this->staff()->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
        $this->redirect(route($route), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.messenger-bell');
    }

    private function staff(): User
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may use Messenger.');
        }

        return $user;
    }
}
