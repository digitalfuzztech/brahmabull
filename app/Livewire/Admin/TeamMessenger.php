<?php

namespace App\Livewire\Admin;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatTeamNotificationService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\E2eeAuthorizationService;
use App\Services\Chat\E2eeDowngradeService;
use App\Services\Chat\TeamInboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class TeamMessenger extends Component
{
    use WithFileUploads;

    public string $section = 'channels';

    public string $search = '';

    public ?int $selectedConversationId = null;

    public array $conversations = [];

    public array $teamMessages = [];

    public array $details = [];

    public string $message = '';

    public $attachment = null;

    public ?int $replyToMessageId = null;

    public bool $showConversationOnMobile = false;

    public bool $showNewMessage = false;

    public bool $showCreateGroup = false;

    public bool $showDetails = false;

    public string $contactSearch = '';

    public array $contacts = [];

    public string $groupName = '';

    public array $selectedMemberIds = [];

    public array $groupCandidates = [];

    public string $renamedGroup = '';

    public ?int $memberToAdd = null;

    public ?string $previewImageUrl = null;

    public ?int $editingMessageId = null;

    public string $editingMessage = '';

    public ?int $pendingDeleteMessageId = null;

    public bool $showDisableE2eeModal = false;

    public function mount(
        TeamInboxService $team,
        BrahmaNoticeboardService $noticeboard,
        ?int $initialConversationId = null,
    ): void {
        $this->staff();
        $noticeboard->ensureAndSyncParticipants();
        $this->refreshList($team);
        if ($initialConversationId) {
            $this->selectTeamConversation($initialConversationId, $team);
        }
    }

    public function updatedSection(TeamInboxService $team): void
    {
        $this->clearSelection();
        $this->refreshList($team);
    }

    #[On('team-section-selected')]
    public function selectSection(string $section, TeamInboxService $team): void
    {
        $allowedSections = ['channels', 'direct', $this->staff()->hasRole('admin') ? 'oversight' : 'groups'];

        abort_unless(in_array($section, $allowedSections, true), 404);

        $this->section = $section;
        $this->clearSelection();
        $this->refreshList($team);
    }

    public function updatedSearch(TeamInboxService $team): void
    {
        $this->refreshList($team);
    }

    public function updatedContactSearch(TeamInboxService $team): void
    {
        if ($this->showNewMessage) {
            $this->loadContacts($team);
        }
    }

    public function selectTeamConversation(int $conversationId, TeamInboxService $team): void
    {
        $conversation = $team->selectConversation($conversationId, $this->staff());
        $this->section = match ($conversation->conversation_type) {
            'internal_channel' => 'channels',
            'internal_group' => $this->staff()->hasRole('admin') ? 'oversight' : 'groups',
            default => 'direct',
        };
        $this->selectedConversationId = $conversation->id;
        $this->showConversationOnMobile = true;
        $this->loadSelected($team, $conversation);
        $this->refreshList($team);
        $this->dispatch('team-messenger-scroll', force: true);
        $this->dispatch('messenger-unread-refresh')->to(MessengerBell::class);
    }

    public function showConversationList(): void
    {
        $this->showConversationOnMobile = false;
    }

    public function pollList(TeamInboxService $team): void
    {
        $this->refreshList($team);
    }

    public function pollSelected(
        TeamInboxService $team,
    ): void {
        if (! $this->selectedConversationId) {
            return;
        }

        try {
            $latestMessageId = collect($this->teamMessages)
                ->last()['id'] ?? null;

            /*
             * IMPORTANT:
             *
             * Polling an already-open conversation must NEVER
             * automatically mark incoming messages as read.
             *
             * Read state is changed only when:
             * 1. user explicitly opens the conversation, or
             * 2. the composer is actively focused.
             */
            $conversation = $team->conversationForView(
                $this->selectedConversationId,
                $this->staff()
            );

            $this->loadSelected(
                $team,
                $conversation
            );

            $newLatestMessageId = collect($this->teamMessages)
                ->last()['id'] ?? null;

            if ($latestMessageId !== $newLatestMessageId) {
                $this->dispatch(
                    'team-messenger-scroll',
                    force: false
                );
            }
        } catch (AuthorizationException) {
            $this->clearSelection();
        }
    }

    public function markSelectedConversationRead(
        TeamInboxService $team,
    ): void {
        if (! $this->selectedConversationId) {
            return;
        }

        try {
            /*
             * selectConversation() intentionally records read state.
             */
            $team->selectConversation(
                $this->selectedConversationId,
                $this->staff()
            );

            /*
             * Immediately refresh left-side unread state.
             */
            $this->refreshList($team);

            /*
             * Immediately refresh top Messenger badge/dropdown.
             */
            $this->dispatch(
                'messenger-unread-refresh'
            )->to(MessengerBell::class);

        } catch (AuthorizationException) {
            $this->clearSelection();
        }
    }

    public function pollTeam(TeamInboxService $team): void
    {
        $this->pollList($team);
        $this->pollSelected($team);
    }

    public function refreshTeam(TeamInboxService $team): void
    {
        $this->pollTeam($team);
    }

    public function openNewMessage(TeamInboxService $team): void
    {
        $this->showNewMessage = true;
        $this->contactSearch = '';
        $this->loadContacts($team);
    }

    public function startDirect(
        int $contactId,
        TeamInboxService $team,
        ConversationService $conversations,
    ): void {
        $contact = $team->contact($this->staff(), $contactId);
        $conversation = $conversations->getOrCreateDirectConversation($this->staff(), $contact);
        $this->showNewMessage = false;
        $this->section = 'direct';
        $this->selectTeamConversation($conversation->id, $team);
    }

    public function openCreateGroup(TeamInboxService $team): void
    {
        if (! $this->staff()->hasRole('agent')) {
            throw new AuthorizationException('Only agents may create groups.');
        }

        $this->showCreateGroup = true;
        $this->groupName = '';
        $this->selectedMemberIds = [];
        $this->groupCandidates = $team->groupCandidates($this->staff(), new ChatConversation)
            ->map->only(['id', 'name', 'username'])->all();
    }

    public function createGroup(
        TeamInboxService $team,
        ConversationService $conversations,
        ChatTeamNotificationService $notifications,
    ): void {
        $this->validate([
            'groupName' => ['required', 'string', 'max:100'],
            'selectedMemberIds' => ['required', 'array', 'min:2'],
            'selectedMemberIds.*' => ['integer', 'distinct'],
        ]);

        $creator = $this->staff();
        $members = collect($this->selectedMemberIds)
            ->map(fn ($id) => $team->activeAgent((int) $id))
            ->unique('id')
            ->values();
        $conversation = $conversations->createGroupConversation($creator, $this->groupName, $members->all());

        foreach ($members as $member) {
            $notifications->notifyAddedToGroup($conversation, $member, $creator);
        }

        $this->showCreateGroup = false;
        $this->section = 'groups';
        $this->selectTeamConversation($conversation->id, $team);
    }

    public function sendTeamMessage(
        TeamInboxService $team,
        ChatMessageService $messages,
        ChatAttachmentService $attachments,
    ): void {
        $this->validate([
            'message' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:'.ChatAttachmentService::MAX_KILOBYTES],
        ]);

        $conversation = $this->currentInternalConversation();
        $actor = $this->staff();
        $replyTo = $this->replyToMessageId
            ? ChatMessage::where('conversation_id', $conversation->id)->findOrFail($this->replyToMessageId)
            : null;

        if ($this->attachment) {
            $storedAttachment = $attachments->sendWithUpload($conversation, $actor, $this->attachment, $this->message, $replyTo);
            $sentMessage = $storedAttachment->message;
        } else {
            $sentMessage = $messages->sendInternalMessage($conversation, $actor, trim($this->message), $replyTo);
        }

        $this->reset(['message', 'attachment', 'replyToMessageId']);
        $this->loadSelected($team, $conversation->fresh());
        $this->refreshList($team);
        $this->dispatch('team-messenger-scroll', force: true);
    }

    public function setReply(int $messageId): void
    {
        $conversation = $this->currentInternalConversation();
        $message = ChatMessage::where('conversation_id', $conversation->id)->findOrFail($messageId);
        app(ChatAuthorizationService::class)->assertCanSendInternal($conversation, $this->staff());
        if ($conversation->conversation_type === 'internal_channel') {
            throw new AuthorizationException('Noticeboard replies are disabled.');
        }
        if ($message->deleted_at !== null) {
            throw new AuthorizationException('Deleted messages cannot be replied to.');
        }
        $this->replyToMessageId = $message->id;
    }

    public function cancelReply(): void
    {
        $this->replyToMessageId = null;
    }

    public function startEdit(int $messageId): void
    {
        $message = ChatMessage::where('conversation_id', $this->currentInternalConversation()->id)->findOrFail($messageId);
        if ($message->encrypted_payload !== null) {
            throw new AuthorizationException('Encrypted messages must be edited in the browser.');
        }
        app(ChatAuthorizationService::class)->assertCanEditInternalMessage($message, $this->staff());
        $this->editingMessageId = $message->id;
        $this->editingMessage = (string) $message->body;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingMessageId', 'editingMessage']);
    }

    public function saveEdit(ChatMessageService $messages, TeamInboxService $team): void
    {
        $this->validate(['editingMessage' => ['nullable', 'string', 'max:2000']]);
        $message = ChatMessage::where('conversation_id', $this->currentInternalConversation()->id)->findOrFail($this->editingMessageId);
        $messages->editInternalMessage($message, $this->staff(), $this->editingMessage);
        $this->cancelEdit();
        $this->loadSelected($team, $this->currentInternalConversation());
    }

    public function deleteMessage(int $messageId, ChatMessageService $messages, TeamInboxService $team): void
    {
        $message = ChatMessage::where('conversation_id', $this->currentInternalConversation()->id)->findOrFail($messageId);
        $messages->deleteInternalMessage($message, $this->staff());
        if ($this->replyToMessageId === $message->id) {
            $this->cancelReply();
        }
        $this->loadSelected($team, $this->currentInternalConversation());
        $this->refreshList($team);
    }

    public function openDeleteMessage(int $messageId): void
    {
        $message = ChatMessage::where('conversation_id', $this->currentInternalConversation()->id)->findOrFail($messageId);
        app(ChatAuthorizationService::class)->assertCanDeleteInternalMessage($message, $this->staff());
        $this->pendingDeleteMessageId = $message->id;
    }

    public function cancelDeleteMessage(): void
    {
        $this->pendingDeleteMessageId = null;
    }

    public function confirmDeleteMessage(ChatMessageService $messages, TeamInboxService $team): void
    {
        if (! $this->pendingDeleteMessageId) {
            return;
        }

        $messageId = $this->pendingDeleteMessageId;
        try {
            $this->deleteMessage($messageId, $messages, $team);
        } finally {
            $this->cancelDeleteMessage();
        }
    }

    public function openDisableE2ee(): void
    {
        $conversation = $this->currentInternalConversation();
        app(E2eeAuthorizationService::class)->assertDirectParticipant($conversation, $this->staff());
        if ($conversation->encryption_mode !== 'e2ee_v1') {
            throw new AuthorizationException('This direct conversation is not E2EE-enabled.');
        }

        $this->showDisableE2eeModal = true;
    }

    public function cancelDisableE2ee(): void
    {
        $this->showDisableE2eeModal = false;
    }

    public function requestDisableE2ee(E2eeDowngradeService $downgrade, TeamInboxService $team): void
    {
        $conversation = $downgrade->requestDisable($this->currentInternalConversation(), $this->staff());
        $this->showDisableE2eeModal = false;
        $this->loadSelected($team, $conversation);
    }

    public function keepE2ee(E2eeDowngradeService $downgrade, TeamInboxService $team): void
    {
        $conversation = $downgrade->keepEncryption($this->currentInternalConversation(), $this->staff());
        $this->loadSelected($team, $conversation);
    }

    public function approveDisableE2ee(E2eeDowngradeService $downgrade, TeamInboxService $team): void
    {
        $conversation = $downgrade->approveDisable($this->currentInternalConversation(), $this->staff());
        $this->loadSelected($team, $conversation);
        $this->refreshList($team);
    }

    public function removeAttachment(): void
    {
        $this->reset('attachment');
    }

    public function react(int $messageId, string $reaction, ChatMessageService $messages, TeamInboxService $team): void
    {
        $conversation = $this->currentInternalConversation();
        $message = ChatMessage::where('conversation_id', $conversation->id)->findOrFail($messageId);
        $existing = $message->reactions()->where('user_id', $this->staff()->id)->first();

        if ($existing?->reaction === $reaction) {
            $messages->removeReaction($message, $this->staff());
        } else {
            $messages->addReaction($message, $this->staff(), $reaction);
        }

        $this->loadSelected($team, $conversation->fresh());
    }

    public function openDetails(TeamInboxService $team): void
    {
        $conversation = $this->currentInternalConversation();
        $this->details = $team->details($conversation, $this->staff());
        $this->renamedGroup = $conversation->name ?? '';
        $this->groupCandidates = $conversation->conversation_type === 'internal_group' && ($this->details['is_owner'] ?? false)
            ? $team->groupCandidates($this->staff(), $conversation)->map->only(['id', 'name', 'username'])->all()
            : [];
        $this->showDetails = true;
    }

    public function renameGroup(ConversationService $conversations, TeamInboxService $team): void
    {
        $this->validate(['renamedGroup' => ['required', 'string', 'max:100']]);
        $conversation = $conversations->renameGroup($this->currentInternalConversation(), $this->staff(), $this->renamedGroup);
        $this->loadSelected($team, $conversation);
        $this->refreshList($team);
    }

    public function addMember(
        ConversationService $conversations,
        TeamInboxService $team,
        ChatTeamNotificationService $notifications,
    ): void {
        $this->validate(['memberToAdd' => ['required', 'integer']]);
        $owner = $this->staff();
        $conversation = $this->currentInternalConversation();
        $member = $team->activeAgent((int) $this->memberToAdd);
        $conversations->addGroupParticipant($conversation, $owner, $member);
        $notifications->notifyAddedToGroup($conversation->fresh(), $member, $owner);
        $this->memberToAdd = null;
        $this->openDetails($team);
        $this->refreshList($team);
    }

    public function removeMember(int $memberId, ConversationService $conversations, TeamInboxService $team): void
    {
        $member = $team->activeAgent($memberId);
        $conversations->removeGroupParticipant($this->currentInternalConversation(), $this->staff(), $member);
        $this->openDetails($team);
        $this->refreshList($team);
    }

    public function leaveGroup(ConversationService $conversations, TeamInboxService $team): void
    {
        $conversations->leaveGroup($this->currentInternalConversation(), $this->staff());
        $this->showDetails = false;
        $this->clearSelection();
        $this->refreshList($team);
    }

    public function previewImage(int $attachmentId, ChatAttachmentService $attachments): void
    {
        $conversation = $this->currentInternalConversation();
        $attachment = ChatMessageAttachment::query()
            ->where('media_type', 'image')
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))
            ->findOrFail($attachmentId);
        $attachments->authorizeRead($attachment, $this->staff());

        $this->previewImageUrl = route('team.attachments.view', $attachment);
    }

    public function closeImagePreview(): void
    {
        $this->previewImageUrl = null;
    }

    public function render()
    {
        return view('livewire.admin.team-messenger', [
            'isAdmin' => $this->staff()->hasRole('admin'),
            'reactions' => ChatMessageService::REACTIONS,
        ]);
    }

    private function refreshList(TeamInboxService $team): void
    {
        $this->conversations = $team->conversations($this->staff(), $this->section, $this->search)->all();
    }

    private function loadSelected(TeamInboxService $team, ChatConversation $conversation): void
    {
        $this->details = $team->details($conversation, $this->staff());
        $this->teamMessages = $team->messages($conversation, $this->staff());
        $this->dispatch('team-e2ee-sync', messages: $this->teamMessages, details: $this->details);
    }

    private function loadContacts(TeamInboxService $team): void
    {
        $this->contacts = $team->contacts($this->staff(), $this->contactSearch)
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->hasRole('admin') ? 'Admin' : 'Agent',
            ])->all();
    }

    private function currentInternalConversation(): ChatConversation
    {
        if (! $this->selectedConversationId) {
            throw new AuthorizationException('Select a Team conversation first.');
        }

        return ChatConversation::whereIn('conversation_type', ['internal_direct', 'internal_group', 'internal_channel'])
            ->findOrFail($this->selectedConversationId);
    }

    private function clearSelection(): void
    {
        $this->selectedConversationId = null;
        $this->teamMessages = [];
        $this->details = [];
        $this->showConversationOnMobile = false;
        $this->replyToMessageId = null;
        $this->editingMessageId = null;
        $this->editingMessage = '';
        $this->pendingDeleteMessageId = null;
        $this->showDisableE2eeModal = false;
    }

    private function staff(): User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may access Team Messenger.');
        }

        return $user;
    }
}
