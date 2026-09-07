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

        return $this->conversations->internalParticipantUnreadCount($staff);
    }

    public function recent(User $staff, int $limit = 15): Collection
    {
        $this->assertStaff($staff);
        $team = ChatConversation::query()
            ->whereIn('conversation_type', ['internal_direct', 'internal_group', 'internal_channel'])
            ->where('is_archived', false)
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
                            ->where(function ($query): void {
                                $query->where(function ($query): void {
                                    $query
                                        ->whereNotNull(
                                            'chat_conversation_participants.last_read_message_id'
                                        )
                                        ->whereColumn(
                                            'chat_messages.id',
                                            '>',
                                            'chat_conversation_participants.last_read_message_id'
                                        );
                                })
                                    ->orWhere(function ($query): void {
                                        $query
                                            ->whereNull(
                                                'chat_conversation_participants.last_read_message_id'
                                            )
                                            ->whereNotNull('chat_conversation_participants.last_read_at')
                                            ->whereColumn(
                                                'chat_messages.created_at',
                                                '>',
                                                'chat_conversation_participants.last_read_at'
                                            );
                                    })
                                    ->orWhere(function ($query): void {
                                        $query
                                            ->whereNull('chat_conversation_participants.last_read_message_id')
                                            ->whereNull('chat_conversation_participants.last_read_at')
                                            ->whereColumn(
                                                'chat_messages.created_at',
                                                '>=',
                                                'chat_conversation_participants.joined_at'
                                            );
                                    });
                            });
                    }),
                'latest_message_id' => ChatMessage::query()
                    ->select('id')
                    ->whereColumn(
                        'chat_messages.conversation_id',
                        'chat_conversations.id'
                    )
                    ->latest('id')
                    ->limit(1),
            ]);

        return $team
            ->orderByDesc('latest_message_id')
            ->orderByDesc('chat_conversations.id')
            ->limit($limit)->get()
            ->map(fn (ChatConversation $conversation) => $this->row(
                $conversation,
                $staff,
                'team',
                (int) $conversation->unread_count,
            ))
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
                : ($conversation->conversation_type === 'internal_direct' && $latest?->encrypted_payload
                    ? ($unread > 0 ? 'New encrypted message' : 'Encrypted message')
                    : (filled($latest?->body) ? $latest->body : ($latest?->attachments->first() ? ucfirst($latest->attachments->first()->media_type) : 'No messages yet'))),
            'unread_count' => $unread,
            'is_observer' => false,
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
