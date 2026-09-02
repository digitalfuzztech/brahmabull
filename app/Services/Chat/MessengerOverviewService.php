<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class MessengerOverviewService
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function unreadCount(User $staff): int
    {
        $this->assertStaff($staff);

        $support = ChatMessage::query()
            ->where('sender_type', 'player')
            ->whereNull('read_by_staff_at')
            ->whereHas('conversation', function ($query) use ($staff): void {
                $query->where('conversation_type', 'support');
                if ($staff->hasRole('agent')) {
                    $query->where(fn ($query) => $query
                        ->where('status', '!=', 'resolved')
                        ->orWhere('assigned_to', $staff->id));
                }
            })->count();

        return $support
            + $this->conversations->internalDirectUnreadCount($staff)
            + $this->conversations->internalGroupUnreadCount($staff)
            + $this->conversations->internalChannelUnreadCount($staff);
    }

    public function recent(User $staff, int $limit = 15): Collection
    {
        $this->assertStaff($staff);
        $support = ChatConversation::query()
            ->where('conversation_type', 'support')
            ->with(['player:id,name,username', 'latestMessage.attachments:id,message_id,media_type'])
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->where('sender_type', 'player')->whereNull('read_by_staff_at')]);

        if ($staff->hasRole('agent')) {
            $support->where(fn ($query) => $query
                ->where('status', '!=', 'resolved')
                ->orWhere('assigned_to', $staff->id));
        }

        $team = ChatConversation::query()
            ->whereIn('conversation_type', ['internal_direct', 'internal_group', 'internal_channel'])
            ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $staff->id))
            ->with(['activeParticipants.user:id,name,username', 'latestMessage.attachments:id,message_id,media_type'])
            ->addSelect([
                'unread_count' => ChatMessage::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('chat_messages.conversation_id', 'chat_conversations.id')
                    ->where('chat_messages.sender_id', '!=', $staff->id)
                    ->whereExists(function ($query) use ($staff): void {
                        $query->selectRaw('1')->from('chat_conversation_participants')
                            ->whereColumn('chat_conversation_participants.conversation_id', 'chat_messages.conversation_id')
                            ->where('chat_conversation_participants.user_id', $staff->id)
                            ->whereNull('chat_conversation_participants.left_at')
                            ->whereColumn('chat_messages.created_at', '>=', 'chat_conversation_participants.joined_at')
                            ->where(fn ($query) => $query->whereNull('chat_conversation_participants.last_read_at')
                                ->orWhereColumn('chat_messages.created_at', '>', 'chat_conversation_participants.last_read_at'));
                    }),
            ]);

        return $support->latest('last_message_at')->limit($limit)->get()
            ->map(fn (ChatConversation $conversation) => $this->row($conversation, $staff, 'support', (int) $conversation->unread_count))
            ->concat($team->latest('last_message_at')->limit($limit)->get()
                ->map(fn (ChatConversation $conversation) => $this->row(
                    $conversation,
                    $staff,
                    'team',
                    (int) $conversation->unread_count,
                )))
            ->sortByDesc('sort_at')
            ->take($limit)
            ->values();
    }

    private function row(ChatConversation $conversation, User $staff, string $domain, int $unread): array
    {
        $latest = $conversation->latestMessage;
        $name = $domain === 'support'
            ? ($conversation->player?->name ?? 'Player Support')
            : (in_array($conversation->conversation_type, ['internal_group', 'internal_channel'], true)
                ? $conversation->name
                : ($conversation->activeParticipants->firstWhere('user_id', '!=', $staff->id)?->user?->name ?? 'Direct Message'));

        return [
            'id' => $conversation->id,
            'domain' => $domain,
            'name' => $name,
            'preview' => $latest?->deleted_at
                ? 'Message deleted'
                : (filled($latest?->body) ? $latest->body : ($latest?->attachments->first() ? ucfirst($latest->attachments->first()->media_type) : 'No messages yet')),
            'unread_count' => $unread,
            'relative_time' => ($conversation->last_message_at ?? $conversation->created_at)?->diffForHumans(),
            'sort_at' => ($conversation->last_message_at ?? $conversation->created_at)?->timestamp ?? 0,
        ];
    }

    private function assertStaff(User $staff): void
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may use Messenger.');
        }
    }
}
