<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatPresenceService;
use App\Services\Chat\MessengerOverviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class MessengerBell extends Component
{
    public int $unreadCount = 0;

    public array $recent = [];

    public int $pollTicks = 0;

    public function mount(
        ChatPresenceService $presence,
        MessengerOverviewService $overview,
        BrahmaNoticeboardService $noticeboard,
    ): void {
        $presence->heartbeat($this->staff());
        $noticeboard->ensureAndSyncParticipants();
        $this->unreadCount = $overview->unreadCount($this->staff());
        $this->recent = $overview->recent($this->staff())->all();
    }

    public function pollUnread(ChatPresenceService $presence, MessengerOverviewService $overview): void
    {
        $this->pollTicks++;
        if ($this->pollTicks % 4 === 0) {
            $presence->heartbeat($this->staff());
        }

        $this->unreadCount = $overview->unreadCount($this->staff());
        $this->recent = $overview->recent($this->staff())->all();
    }

    public function refreshMessenger(ChatPresenceService $presence, MessengerOverviewService $overview): void
    {
        $this->pollUnread($presence, $overview);
    }

    #[On('messenger-unread-refresh')]
    public function refreshUnread(MessengerOverviewService $overview): void
    {
        $this->unreadCount = $overview->unreadCount($this->staff());
        $this->recent = $overview->recent($this->staff())->all();
    }

    #[On('team-message-arrived')]
    public function handleTeamMessageArrived(
        MessengerOverviewService $overview,
        array $conversationIds = [],
        array $messageIds = [],
    ): void {
        /*
         * A new Team message has been detected by the working
         * global activity poll.
         *
         * Re-query BOTH:
         * - total Team unread
         * - warm dropdown rows
         */
        $this->unreadCount = $overview->unreadCount(
            $this->staff()
        );

        $this->recent = $overview->recent(
            $this->staff()
        )->all();
    }

    public function openConversation(int $conversationId): void
    {
        $route = $this->staff()->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
        $this->redirect(route($route, ['domain' => 'team', 'conversation' => $conversationId]), navigate: true);
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
