<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationObserverRead;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class ChatObserverReadService
{
    public function __construct(private readonly ChatAuthorizationService $authorization) {}

    public function markRead(
        ChatConversation $conversation,
        User $observer
    ): void {
        if (
            ! $this->authorization
                ->canReadInternalGroupAsAdmin(
                    $conversation,
                    $observer
                )
        ) {
            throw new AuthorizationException(
                'This group is not available for Admin oversight.'
            );
        }

        $lastMessageId = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->max('id');

        ChatConversationObserverRead::query()
            ->updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $observer->id,
                ],
                [
                    'last_read_at' => now(),
                    'last_read_message_id' => $lastMessageId
                        ? (int) $lastMessageId
                        : null,
                ],
            );
    }

    public function unreadCount(User $observer): int
    {
        if (! $observer->hasRole('admin')) {
            return 0;
        }

        return $this->unreadMessagesQuery($observer)->count();
    }

    public function unreadCountForConversation(ChatConversation $conversation, User $observer): int
    {
        if (! $this->authorization->canReadInternalGroupAsAdmin($conversation, $observer)) {
            return 0;
        }

        return $this->unreadMessagesQuery($observer)
            ->where('chat_messages.conversation_id', $conversation->id)
            ->count();
    }

    public function qualifyingConversationIds(): Builder
    {
        return ChatConversationParticipant::query()
            ->select('conversation_id')
            ->whereNull('left_at')
            ->whereHas('user.roles', fn ($query) => $query->where('name', 'agent'))
            ->groupBy('conversation_id')
            ->havingRaw('COUNT(*) >= 3');
    }

    private function unreadMessagesQuery(User $observer)
    {
        return ChatMessage::query()
            ->join('chat_conversations', 'chat_conversations.id', '=', 'chat_messages.conversation_id')
            ->where('chat_conversations.conversation_type', 'internal_group')
            ->where('chat_conversations.is_archived', false)
            ->whereIn('chat_conversations.id', $this->qualifyingConversationIds())
            ->whereNotNull('chat_messages.sender_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('chat_conversation_participants as sender_participant')
                    ->whereColumn('sender_participant.conversation_id', 'chat_messages.conversation_id')
                    ->whereColumn('sender_participant.user_id', 'chat_messages.sender_id');
            })
            ->where(function ($query) use ($observer): void {
                $query->whereNotExists(function ($query) use ($observer): void {
                    $query->selectRaw('1')
                        ->from('chat_conversation_observer_reads')
                        ->whereColumn('chat_conversation_observer_reads.conversation_id', 'chat_messages.conversation_id')
                        ->where('chat_conversation_observer_reads.user_id', $observer->id);
                })->orWhereExists(function ($query) use ($observer): void {
                    $query->selectRaw('1')
                        ->from('chat_conversation_observer_reads')
                        ->whereColumn('chat_conversation_observer_reads.conversation_id', 'chat_messages.conversation_id')
                        ->where('chat_conversation_observer_reads.user_id', $observer->id)
                        ->where(function ($query): void {
                            $query->where(function ($query): void {
                                $query
                                    ->whereNotNull(
                                        'chat_conversation_observer_reads.last_read_message_id'
                                    )
                                    ->whereColumn(
                                        'chat_messages.id',
                                        '>',
                                        'chat_conversation_observer_reads.last_read_message_id'
                                    );
                            })
                                ->orWhere(function ($query): void {
                                    $query
                                        ->whereNull(
                                            'chat_conversation_observer_reads.last_read_message_id'
                                        )
                                        ->where(function ($query): void {
                                            $query
                                                ->whereNull(
                                                    'chat_conversation_observer_reads.last_read_at'
                                                )
                                                ->orWhereColumn(
                                                    'chat_messages.created_at',
                                                    '>',
                                                    'chat_conversation_observer_reads.last_read_at'
                                                );
                                        });
                                });
                        });
                });
            });
    }
}
