<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeamInboxService
{
    public function __construct(
        private readonly ChatAuthorizationService $authorization,
        private readonly ConversationService $conversations,
        private readonly ChatPresenceService $presence,
    ) {}

    public function conversations(User $staff, string $section, string $search = ''): Collection
    {
        $this->assertStaff($staff);
        $section = $this->normalizeSection($staff, $section);
        $query = ChatConversation::query()
            ->where('is_archived', false)
            ->with([
                'latestMessage.attachments:id,message_id,media_type',
                'activeParticipants.user.roles:id,name',
            ])
            ->addSelect([
                'unread_count' => ChatMessage::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('chat_messages.conversation_id', 'chat_conversations.id')
                    ->where('chat_messages.sender_id', '!=', $staff->id)
                    ->whereExists(function ($query) use ($staff): void {
                        $query->selectRaw('1')
                            ->from('chat_conversation_participants')
                            ->whereColumn('chat_conversation_participants.conversation_id', 'chat_messages.conversation_id')
                            ->where('chat_conversation_participants.user_id', $staff->id)
                            ->whereNull('chat_conversation_participants.left_at')
                            ->whereColumn('chat_messages.created_at', '>=', 'chat_conversation_participants.joined_at')
                            ->where(function ($query): void {
                                $query->whereNull('chat_conversation_participants.last_read_at')
                                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_conversation_participants.last_read_at');
                            });
                    }),
            ]);

        if ($section === 'channels') {
            $query->where('conversation_type', 'internal_channel')
                ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $staff->id));
        } elseif ($section === 'direct') {
            $query->where('conversation_type', 'internal_direct')
                ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $staff->id));
        } elseif ($staff->hasRole('admin')) {
            $query->where('conversation_type', 'internal_group')
                ->whereIn('id', ChatConversationParticipant::query()
                    ->select('conversation_id')
                    ->whereNull('left_at')
                    ->whereHas('user.roles', fn ($query) => $query->where('name', 'agent'))
                    ->groupBy('conversation_id')
                    ->havingRaw('COUNT(*) >= 3'));
        } else {
            $query->where('conversation_type', 'internal_group')
                ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $staff->id));
        }

        if ($search = trim($search)) {
            $query->where(function ($query) use ($search, $staff): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('activeParticipants.user', function ($query) use ($search, $staff): void {
                        $query->where('users.id', '!=', $staff->id)
                            ->where(function ($query) use ($search): void {
                                $query->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('username', 'like', '%'.$search.'%');
                            });
                    });
            });
        }

        return $query->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->limit(75)
            ->get()
            ->map(function (ChatConversation $conversation) use ($staff): array {
                $isParticipant = $conversation->activeParticipants->contains('user_id', $staff->id);
                $latestAttachment = $conversation->latestMessage?->attachments->first();
                $otherParticipant = $conversation->conversation_type === 'internal_direct'
                    ? $conversation->activeParticipants->firstWhere('user_id', '!=', $staff->id)?->user
                    : null;

                return [
                    'id' => $conversation->id,
                    'type' => $conversation->conversation_type,
                    'name' => $this->displayName($conversation, $staff),
                    'preview' => $conversation->latestMessage?->deleted_at
                        ? 'Message deleted'
                        : (filled($conversation->latestMessage?->body)
                        ? $conversation->latestMessage->body
                        : ($latestAttachment ? ucfirst($latestAttachment->media_type) : 'No messages yet')),
                    'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toISOString(),
                    'member_count' => $conversation->activeParticipants->count(),
                    'unread_count' => $isParticipant ? (int) $conversation->unread_count : 0,
                    'is_observer' => ! $isParticipant,
                    'other_online' => $otherParticipant ? $this->presence->isOnline($otherParticipant) : null,
                ];
            });
    }

    public function selectConversation(int $conversationId, User $staff): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->whereIn('conversation_type', ['internal_direct', 'internal_group', 'internal_channel'])
            ->where('is_archived', false)
            ->findOrFail($conversationId);
        $this->authorization->assertCanViewInternal($conversation, $staff);

        if ($this->authorization->isActiveParticipant($conversation, $staff)) {
            $this->conversations->markInternalConversationRead($conversation, $staff);
        }

        return $conversation;
    }

    public function messages(ChatConversation $conversation, User $staff, int $limit = 100): array
    {
        $this->authorization->assertCanViewInternal($conversation, $staff);

        return $conversation->messages()
            ->with([
                'sender:id,name,username',
                'replyTo.sender:id,name,username',
                'attachments:id,message_id,media_type,original_name,mime_type,file_size,width,height,duration',
                'reactions.user:id,name',
            ])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'body' => $message->deleted_at ? null : $message->body,
                'deleted' => $message->deleted_at !== null,
                'edited_at' => $message->edited_at?->toISOString(),
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name ?? 'Former staff',
                'sender_role' => $message->sender_type,
                'created_at' => $message->created_at?->toISOString(),
                'reply' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'sender_name' => $message->replyTo->sender?->name ?? 'Former staff',
                    'body' => $message->replyTo->deleted_at ? 'This message was deleted.' : ($message->replyTo->body ?: 'Attachment'),
                    'deleted' => $message->replyTo->deleted_at !== null,
                ] : null,
                'attachments' => $message->deleted_at ? [] : $message->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'media_type' => $attachment->media_type,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'view_url' => route('team.attachments.view', $attachment),
                    'download_url' => route('team.attachments.download', $attachment),
                ])->all(),
                'reactions' => $message->deleted_at ? [] : $message->reactions
                    ->groupBy('reaction')
                    ->map(fn ($reactions, $reaction) => [
                        'reaction' => $reaction,
                        'count' => $reactions->count(),
                        'user_ids' => $reactions->pluck('user_id')->all(),
                    ])->values()->all(),
                'can_edit' => $message->deleted_at === null && filled($message->body) && $message->sender_id === $staff->id,
                'can_delete' => $message->deleted_at === null && $message->sender_id === $staff->id,
            ])->all();
    }

    public function details(ChatConversation $conversation, User $staff): array
    {
        $this->authorization->assertCanViewInternal($conversation, $staff);
        $conversation->load(['activeParticipants.user.roles:id,name']);
        $participant = $conversation->activeParticipants->firstWhere('user_id', $staff->id);
        $otherParticipant = $conversation->conversation_type === 'internal_direct'
            ? $conversation->activeParticipants->firstWhere('user_id', '!=', $staff->id)?->user
            : null;

        return [
            'id' => $conversation->id,
            'type' => $conversation->conversation_type,
            'name' => $this->displayName($conversation, $staff),
            'member_count' => $conversation->activeParticipants->count(),
            'is_observer' => $participant === null,
            'can_send' => $participant !== null
                && ($conversation->conversation_type !== 'internal_channel' || $staff->hasRole('admin')),
            'can_reply' => $participant !== null && $conversation->conversation_type !== 'internal_channel',
            'can_react' => $participant !== null && $conversation->conversation_type !== 'internal_channel',
            'is_owner' => $participant?->participant_role === 'owner',
            'other_online' => $otherParticipant ? $this->presence->isOnline($otherParticipant) : null,
            'members' => $conversation->activeParticipants->map(fn (ChatConversationParticipant $member) => [
                'id' => $member->user_id,
                'name' => $member->user?->name,
                'username' => $member->user?->username,
                'role' => $member->participant_role,
            ])->all(),
        ];
    }

    public function contacts(User $staff, string $search = ''): Collection
    {
        $this->assertStaff($staff);
        $query = $this->contactQuery($staff);

        if ($search = trim($search)) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%');
            });
        }

        return $query->with('roles:id,name')->orderBy('name')->limit(50)->get(['id', 'name', 'username']);
    }

    public function contact(User $staff, int $contactId): User
    {
        $this->assertStaff($staff);

        $contact = $this->contactQuery($staff)->find($contactId);

        if (! $contact) {
            throw new AuthorizationException('The selected Team contact is not available.');
        }

        return $contact;
    }

    private function contactQuery(User $staff): Builder
    {
        $query = User::query()->where('is_active', true)->whereKeyNot($staff->id);

        if ($staff->hasRole('admin')) {
            $query->role('agent');
        } else {
            $query->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']));
        }

        return $query;
    }

    public function groupCandidates(User $agent, ChatConversation $conversation): Collection
    {
        if (! $agent->hasRole('agent')) {
            throw new AuthorizationException('Only agents manage internal groups.');
        }

        if ($conversation->exists) {
            $this->authorization->assertCanManageGroup($conversation, $agent);
        }

        $existingIds = $conversation->exists
            ? $conversation->activeParticipants()->pluck('user_id')
            : collect([$agent->id]);

        return User::role('agent')
            ->where('is_active', true)
            ->whereNotIn('id', $existingIds)
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
    }

    public function activeAgent(int $agentId): User
    {
        $agent = User::role('agent')->where('is_active', true)->find($agentId);

        if (! $agent) {
            throw new AuthorizationException('The selected user is not an active agent.');
        }

        return $agent;
    }

    private function displayName(ChatConversation $conversation, User $viewer): string
    {
        if (in_array($conversation->conversation_type, ['internal_group', 'internal_channel'], true)) {
            return $conversation->name;
        }

        return $conversation->activeParticipants
            ->first(fn (ChatConversationParticipant $participant) => $participant->user_id !== $viewer->id)
            ?->user?->name ?? 'Direct Message';
    }

    private function normalizeSection(User $staff, string $section): string
    {
        $allowed = $staff->hasRole('admin')
            ? ['channels', 'direct', 'oversight']
            : ['channels', 'direct', 'groups'];

        return in_array($section, $allowed, true) ? $section : 'channels';
    }

    private function assertStaff(User $staff): void
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may access Team Messenger.');
        }
    }
}
