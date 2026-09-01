<?php

namespace App\Livewire\Admin;

use App\Exceptions\ConversationAlreadyHandledException;
use App\Models\ChatConversation;
use App\Models\ChatSupportEvent;
use App\Models\User;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatSupportNotificationService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\SupportInboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class SupportInbox extends Component
{
    #[Url]
    public string $filter = 'waiting';

    #[Url]
    public string $search = '';

    public ?int $selectedConversationId = null;

    public ?int $selectedAgentId = null;

    public string $message = '';

    public array $conversations = [];

    public array $timeline = [];

    public array $selectedConversation = [];

    public array $playerContext = [];

    public array $supportEvents = [];

    public array $agents = [];

    public bool $showConversationOnMobile = false;

    public function mount(SupportInboxService $inbox): void
    {
        $staff = $this->staff();

        if ($staff->hasRole('agent') && ! in_array($this->filter, ['waiting', 'mine', 'resolved'], true)) {
            $this->filter = 'waiting';
        }

        $this->agents = $staff->hasRole('admin')
            ? $inbox->activeAgents($staff)->map->only(['id', 'name', 'username'])->all()
            : [];
        $this->refreshList($inbox);
    }

    public function updatedFilter(SupportInboxService $inbox): void
    {
        $this->selectedConversationId = null;
        $this->showConversationOnMobile = false;
        $this->refreshList($inbox);
    }

    public function updatedSearch(SupportInboxService $inbox): void
    {
        $this->refreshList($inbox);
    }

    public function selectConversation(int $conversationId, SupportInboxService $inbox): void
    {
        $conversation = $inbox->selectConversation($conversationId, $this->staff());
        $this->selectedConversationId = $conversation->id;
        $this->selectedAgentId = $conversation->assigned_to;
        $this->showConversationOnMobile = true;
        $this->loadSelected($inbox, $conversation, true);
        $this->refreshList($inbox);
        $this->dispatch('support-inbox-scroll');
    }

    public function showConversationList(): void
    {
        $this->showConversationOnMobile = false;
    }

    public function pollInbox(SupportInboxService $inbox): void
    {
        $this->refreshList($inbox);

        if (! $this->selectedConversationId) {
            return;
        }

        try {
            $conversation = $inbox->selectConversation($this->selectedConversationId, $this->staff());
            $this->loadSelected($inbox, $conversation, false);
        } catch (AuthorizationException) {
            $this->clearSelection();
        }
    }

    public function takeConversation(
        ConversationService $conversations,
        SupportInboxService $inbox,
    ): void {
        $conversation = $this->currentSupportConversation();

        try {
            $taken = $conversations->takeConversation($conversation, $this->staff());
            $this->loadSelected($inbox, $taken, true);
            $this->refreshList($inbox);
        } catch (ConversationAlreadyHandledException) {
            $this->addError('conversation', 'This conversation is already being handled.');
            $this->pollInbox($inbox);
        }
    }

    public function assignConversation(
        ConversationService $conversations,
        SupportInboxService $inbox,
        ChatSupportNotificationService $notifications,
    ): void {
        $this->validate(['selectedAgentId' => ['required', 'integer']]);
        $admin = $this->staff();
        $agent = $inbox->activeAgent($admin, (int) $this->selectedAgentId);

        try {
            $assigned = $conversations->assignConversation($this->currentSupportConversation(), $admin, $agent);
            $notifications->notifyConversationAssigned($assigned, $agent, $admin);
            $this->loadSelected($inbox, $assigned, true);
            $this->refreshList($inbox);
        } catch (ConversationAlreadyHandledException) {
            $this->addError('conversation', 'This conversation is already being handled.');
            $this->pollInbox($inbox);
        }
    }

    public function reassignConversation(
        ConversationService $conversations,
        SupportInboxService $inbox,
        ChatSupportNotificationService $notifications,
    ): void {
        $this->validate(['selectedAgentId' => ['required', 'integer']]);
        $admin = $this->staff();
        $agent = $inbox->activeAgent($admin, (int) $this->selectedAgentId);
        $assigned = $conversations->reassignConversation($this->currentSupportConversation(), $admin, $agent);
        $notifications->notifyConversationAssigned($assigned, $agent, $admin);
        $this->loadSelected($inbox, $assigned, true);
        $this->refreshList($inbox);
    }

    public function sendReply(ChatMessageService $messages, SupportInboxService $inbox): void
    {
        $validated = $this->validate([
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => 'Please enter a reply.',
            'message.max' => 'Replies may not be longer than 2000 characters.',
        ]);

        $conversation = $this->currentSupportConversation();
        $messages->sendStaffMessage($conversation, $this->staff(), trim($validated['message']));
        $this->message = '';
        $this->loadSelected($inbox, $conversation->fresh(), true);
        $this->refreshList($inbox);
        $this->dispatch('support-inbox-scroll');
    }

    public function resolveConversation(ConversationService $conversations, SupportInboxService $inbox): void
    {
        $resolved = $conversations->resolveConversation($this->currentSupportConversation(), $this->staff());
        $this->loadSelected($inbox, $resolved, true);
        $this->refreshList($inbox);
    }

    public function markEventHandled(int $eventId, SupportInboxService $inbox): void
    {
        $conversation = $this->currentSupportConversation();
        $event = ChatSupportEvent::whereKey($eventId)->firstOrFail();
        $inbox->markEventHandled($event, $conversation, $this->staff());
        $this->supportEvents = $inbox->supportEvents($conversation->fresh(), $this->staff());
    }

    public function render()
    {
        return view('livewire.admin.support-inbox', [
            'isAdmin' => $this->staff()->hasRole('admin'),
        ])->layout('layouts.private');
    }

    private function refreshList(SupportInboxService $inbox): void
    {
        $this->conversations = $inbox->conversations($this->staff(), $this->filter, $this->search)
            ->map(fn (ChatConversation $conversation) => [
                'id' => $conversation->id,
                'reference' => $conversation->reference,
                'player_name' => $conversation->player?->name,
                'player_username' => $conversation->player?->username,
                'preview' => $conversation->latest_message_preview,
                'status' => $conversation->status,
                'assigned_name' => $conversation->assignedStaff?->name,
                'unread_count' => (int) $conversation->unread_count,
                'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toISOString(),
            ])->all();
    }

    private function loadSelected(
        SupportInboxService $inbox,
        ChatConversation $conversation,
        bool $includeContext,
    ): void {
        $conversation = $inbox->selectConversation($conversation->id, $this->staff());
        $this->selectedAgentId = $conversation->assigned_to;
        $this->selectedConversation = [
            'id' => $conversation->id,
            'reference' => $conversation->reference,
            'status' => $conversation->status,
            'assigned_to' => $conversation->assigned_to,
            'assigned_name' => $conversation->assignedStaff?->name,
            'player_name' => $conversation->player?->name,
            'player_username' => $conversation->player?->username,
        ];
        $this->timeline = $inbox->timeline($conversation, $this->staff());

        if ($includeContext) {
            $this->playerContext = $inbox->playerContext($conversation, $this->staff());
            $this->supportEvents = $inbox->supportEvents($conversation, $this->staff());
        }
    }

    private function currentSupportConversation(): ChatConversation
    {
        if (! $this->selectedConversationId) {
            throw new AuthorizationException('Select a support conversation first.');
        }

        return ChatConversation::where('conversation_type', 'support')->findOrFail($this->selectedConversationId);
    }

    private function clearSelection(): void
    {
        $this->selectedConversationId = null;
        $this->selectedConversation = [];
        $this->timeline = [];
        $this->playerContext = [];
        $this->supportEvents = [];
        $this->showConversationOnMobile = false;
    }

    private function staff(): User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only support staff may access the Inbox.');
        }

        return $user;
    }
}
