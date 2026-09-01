<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ChatMessageService
{
    public const REACTIONS = [
        "\u{1F44D}",
        "\u{2764}\u{FE0F}",
        "\u{1F602}",
        "\u{1F62E}",
        "\u{1F622}",
        "\u{1F64F}",
    ];

    public function __construct(private readonly ChatAuthorizationService $authorization) {}

    public function sendPlayerMessage(
        ChatConversation $conversation,
        User $player,
        ?string $body,
        string $messageType = 'text',
        array $metadata = [],
    ): ChatMessage {
        return DB::transaction(function () use ($conversation, $player, $body, $messageType, $metadata): ChatMessage {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertPlayerOwns($locked, $player);
            $this->assertOpen($locked);

            return $this->createMessage($locked, 'player', $player->id, $body, $messageType, $metadata);
        });
    }

    public function sendBotMessage(
        ChatConversation $conversation,
        ?string $body,
        string $messageType = 'text',
        array $metadata = [],
    ): ChatMessage {
        return $this->sendAutomatedMessage($conversation, 'bot', $body, $messageType, $metadata);
    }

    public function sendBotMessageIfConversationEmpty(
        ChatConversation $conversation,
        ?string $body,
        string $messageType = 'text',
        array $metadata = [],
    ): ?ChatMessage {
        return DB::transaction(function () use ($conversation, $body, $messageType, $metadata): ?ChatMessage {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            if ($locked->conversation_type !== 'support') {
                throw new DomainException('Automated messages are available only in support conversations.');
            }

            $this->assertOpen($locked);

            if ($locked->messages()->exists()) {
                return null;
            }

            return $this->createMessage($locked, 'bot', null, $body, $messageType, $metadata);
        });
    }

    public function sendSystemMessage(
        ChatConversation $conversation,
        ?string $body,
        string $messageType = 'system',
        array $metadata = [],
    ): ChatMessage {
        return $this->sendAutomatedMessage($conversation, 'system', $body, $messageType, $metadata);
    }

    public function sendStaffMessage(
        ChatConversation $conversation,
        User $staff,
        ?string $body,
        string $messageType = 'text',
        array $metadata = [],
    ): ChatMessage {
        return DB::transaction(function () use ($conversation, $staff, $body, $messageType, $metadata): ChatMessage {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanReply($locked, $staff);

            if ($locked->status === 'waiting' && $locked->assigned_to === null) {
                $locked->update([
                    'status' => 'active',
                    'assigned_to' => $staff->id,
                    'assigned_by' => $staff->id,
                    'assigned_at' => now(),
                ]);
            }

            $senderType = $staff->hasRole('admin') ? 'admin' : 'agent';

            return $this->createMessage($locked, $senderType, $staff->id, $body, $messageType, $metadata);
        });
    }

    public function markMessagesReadByPlayer(ChatConversation $conversation, User $player): int
    {
        $this->authorization->assertPlayerOwns($conversation, $player);

        return $conversation->messages()
            ->where('sender_type', '!=', 'player')
            ->whereNull('read_by_player_at')
            ->update(['read_by_player_at' => now()]);
    }

    public function markMessagesReadByStaff(ChatConversation $conversation, User $staff): int
    {
        if ($conversation->conversation_type !== 'support') {
            throw new DomainException('Internal conversations use participant read state.');
        }

        $this->authorization->assertCanView($conversation, $staff);

        return $conversation->messages()
            ->where('sender_type', 'player')
            ->whereNull('read_by_staff_at')
            ->update(['read_by_staff_at' => now()]);
    }

    public function sendInternalMessage(
        ChatConversation $conversation,
        User $sender,
        string $body,
        ?ChatMessage $replyTo = null,
    ): ChatMessage {
        return DB::transaction(function () use ($conversation, $sender, $body, $replyTo): ChatMessage {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanSendInternal($locked, $sender);
            $this->assertValidInternalReply($locked, $replyTo);
            $senderType = $sender->hasRole('admin') ? 'admin' : 'agent';

            return $this->createMessage($locked, $senderType, $sender->id, $body, 'text', [], $replyTo?->id);
        });
    }

    public function replyToInternalMessage(
        ChatConversation $conversation,
        ChatMessage $replyTo,
        User $sender,
        string $body,
    ): ChatMessage {
        return $this->sendInternalMessage($conversation, $sender, $body, $replyTo);
    }

    public function addReaction(ChatMessage $message, User $user, string $reaction): ChatMessageReaction
    {
        if (! in_array($reaction, self::REACTIONS, true)) {
            throw new DomainException('Unsupported chat reaction.');
        }

        return DB::transaction(function () use ($message, $user, $reaction): ChatMessageReaction {
            $lockedMessage = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $conversation = ChatConversation::whereKey($lockedMessage->conversation_id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanReact($conversation, $user);

            return ChatMessageReaction::updateOrCreate(
                ['message_id' => $lockedMessage->id, 'user_id' => $user->id],
                ['reaction' => $reaction],
            );
        });
    }

    public function removeReaction(ChatMessage $message, User $user): void
    {
        DB::transaction(function () use ($message, $user): void {
            $lockedMessage = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $conversation = ChatConversation::whereKey($lockedMessage->conversation_id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanReact($conversation, $user);
            ChatMessageReaction::where('message_id', $lockedMessage->id)
                ->where('user_id', $user->id)
                ->delete();
        });
    }

    private function sendAutomatedMessage(
        ChatConversation $conversation,
        string $senderType,
        ?string $body,
        string $messageType,
        array $metadata,
    ): ChatMessage {
        return DB::transaction(function () use ($conversation, $senderType, $body, $messageType, $metadata): ChatMessage {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if ($locked->conversation_type !== 'support') {
                throw new DomainException('Automated messages are available only in support conversations.');
            }
            $this->assertOpen($locked);

            return $this->createMessage($locked, $senderType, null, $body, $messageType, $metadata);
        });
    }

    private function createMessage(
        ChatConversation $conversation,
        string $senderType,
        ?int $senderId,
        ?string $body,
        string $messageType,
        array $metadata,
        ?int $replyToMessageId = null,
    ): ChatMessage {
        if (! in_array($messageType, ['text', 'options', 'system'], true)) {
            throw new DomainException('Unsupported chat message type.');
        }

        if (($body === null || trim($body) === '') && $metadata === []) {
            throw new DomainException('A chat message must contain body text or metadata.');
        }

        $now = now();
        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'reply_to_message_id' => $replyToMessageId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message_type' => $messageType,
            'body' => $body,
            'metadata' => $metadata ?: null,
            'read_by_player_at' => $senderType === 'player' ? $now : null,
            'read_by_staff_at' => in_array($senderType, ['admin', 'agent', 'bot', 'system'], true) ? $now : null,
        ]);

        $conversationUpdates = ['last_message_at' => $now];

        if ($conversation->conversation_type === 'support') {
            if ($senderType === 'player') {
                $conversationUpdates['last_player_message_at'] = $now;
            } elseif (in_array($senderType, ['admin', 'agent'], true)) {
                $conversationUpdates['last_staff_message_at'] = $now;
                $conversationUpdates['first_staff_response_at'] = $conversation->first_staff_response_at ?? $now;
            }
        } else {
            $conversation->participants()
                ->where('user_id', $senderId)
                ->whereNull('left_at')
                ->update(['last_read_at' => $now]);
        }

        $conversation->update($conversationUpdates);

        return $message;
    }

    private function assertOpen(ChatConversation $conversation): void
    {
        if (! in_array($conversation->status, ChatConversation::OPEN_STATUSES, true)) {
            throw new DomainException('Messages cannot be added to a resolved conversation.');
        }
    }

    private function assertValidInternalReply(ChatConversation $conversation, ?ChatMessage $replyTo): void
    {
        if ($replyTo && $replyTo->conversation_id !== $conversation->id) {
            throw new DomainException('A reply target must belong to the same conversation.');
        }
    }
}
