<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class E2eeReactionService
{
    public function __construct(
        private readonly E2eeMessageService $encryptedMessages,
        private readonly E2eeEnvelopeValidator $envelopes,
    ) {}

    public function store(
        ChatMessage $message,
        User $actor,
        string $encryptedReaction,
        int $encryptionVersion,
        int $keyVersion,
    ): ChatMessageReaction {
        return DB::transaction(function () use ($message, $actor, $encryptedReaction, $encryptionVersion, $keyVersion): ChatMessageReaction {
            $lockedMessage = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $conversation = ChatConversation::whereKey($lockedMessage->conversation_id)->lockForUpdate()->firstOrFail();
            $this->encryptedMessages->assertActivatedDirect($conversation, $actor);

            if ($lockedMessage->deleted_at !== null || $lockedMessage->encrypted_payload === null) {
                throw new AuthorizationException('Encrypted reactions require an active encrypted message.');
            }

            $envelope = $this->envelopes->validate($encryptedReaction);
            if ($encryptionVersion !== E2eeEnvelopeValidator::FORMAT_VERSION
                || $keyVersion !== (int) $conversation->current_key_version
                || $envelope['v'] !== $encryptionVersion
                || $envelope['key_version'] !== $keyVersion) {
                throw new DomainException('The encrypted reaction key or format version is not current.');
            }

            return ChatMessageReaction::updateOrCreate(
                ['message_id' => $lockedMessage->id, 'user_id' => $actor->id],
                [
                    'reaction' => '',
                    'encrypted_reaction' => json_encode($envelope, JSON_THROW_ON_ERROR),
                    'encryption_version' => $encryptionVersion,
                    'key_version' => $keyVersion,
                ],
            );
        });
    }

    public function remove(ChatMessage $message, User $actor): void
    {
        DB::transaction(function () use ($message, $actor): void {
            $lockedMessage = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $conversation = ChatConversation::whereKey($lockedMessage->conversation_id)->lockForUpdate()->firstOrFail();
            $this->encryptedMessages->assertActivatedDirect($conversation, $actor);

            ChatMessageReaction::query()
                ->where('message_id', $lockedMessage->id)
                ->where('user_id', $actor->id)
                ->whereNotNull('encrypted_reaction')
                ->delete();
        });
    }
}
