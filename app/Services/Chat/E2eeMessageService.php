<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatE2eeConversationKey;
use App\Models\ChatE2eeDevice;
use App\Models\ChatMessage;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class E2eeMessageService
{
    public function __construct(
        private readonly E2eeAuthorizationService $e2eeAuthorization,
        private readonly ChatAuthorizationService $chatAuthorization,
        private readonly E2eeEnvelopeValidator $envelopes,
    ) {}

    public function send(
        ChatConversation $conversation,
        User $sender,
        string $clientMessageUuid,
        string $encryptedPayload,
        int $encryptionVersion,
        int $keyVersion,
        ?int $replyToMessageId = null,
    ): ChatMessage {
        return DB::transaction(function () use ($conversation, $sender, $clientMessageUuid, $encryptedPayload, $encryptionVersion, $keyVersion, $replyToMessageId): ChatMessage {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->assertActivatedDirect($locked, $sender);
            $envelope = $this->validateMessageEnvelope($locked, $clientMessageUuid, $encryptedPayload, $encryptionVersion, $keyVersion);

            if (ChatMessage::where('client_message_uuid', $clientMessageUuid)->exists()) {
                throw new DomainException('The encrypted client message UUID has already been used.');
            }

            $replyTo = $replyToMessageId === null
                ? null
                : ChatMessage::where('conversation_id', $locked->id)->findOrFail($replyToMessageId);

            $now = now();
            $message = ChatMessage::create([
                'conversation_id' => $locked->id,
                'reply_to_message_id' => $replyTo?->id,
                'sender_type' => $sender->hasRole('admin') ? 'admin' : 'agent',
                'sender_id' => $sender->id,
                'message_type' => 'text',
                'client_message_uuid' => $clientMessageUuid,
                'body' => null,
                'encrypted_payload' => json_encode($envelope, JSON_THROW_ON_ERROR),
                'encryption_version' => $encryptionVersion,
                'key_version' => $keyVersion,
            ]);

            $locked->participants()->where('user_id', $sender->id)->whereNull('left_at')->update(['last_read_at' => $now]);
            $locked->update(['last_message_at' => $now]);

            return $message;
        });
    }

    public function edit(ChatMessage $message, User $sender, string $encryptedPayload, int $encryptionVersion, int $keyVersion): ChatMessage
    {
        return DB::transaction(function () use ($message, $sender, $encryptedPayload, $encryptionVersion, $keyVersion): ChatMessage {
            $lockedMessage = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $conversation = ChatConversation::whereKey($lockedMessage->conversation_id)->lockForUpdate()->firstOrFail();
            $this->assertActivatedDirect($conversation, $sender);

            if ($lockedMessage->sender_id !== $sender->id || $lockedMessage->deleted_at !== null || ! $lockedMessage->encrypted_payload) {
                throw new AuthorizationException('Only the original sender may edit this encrypted message.');
            }

            $envelope = $this->validateMessageEnvelope(
                $conversation,
                (string) $lockedMessage->client_message_uuid,
                $encryptedPayload,
                $encryptionVersion,
                $keyVersion,
            );

            $lockedMessage->update([
                'body' => null,
                'encrypted_payload' => json_encode($envelope, JSON_THROW_ON_ERROR),
                'encryption_version' => $encryptionVersion,
                'key_version' => $keyVersion,
                'edited_at' => now(),
            ]);

            return $lockedMessage->fresh();
        });
    }

    public function assertActivatedDirect(ChatConversation $conversation, User $sender): void
    {
        $this->e2eeAuthorization->assertDirectParticipant($conversation, $sender);
        $this->chatAuthorization->assertCanSendInternal($conversation, $sender);

        if ($conversation->encryption_mode !== 'e2ee_v1'
            || $conversation->e2ee_enabled_at === null
            || ! $conversation->current_key_version) {
            throw new AuthorizationException('This direct conversation is not E2EE-enabled.');
        }

        if ($conversation->e2ee_rotation_required_at !== null) {
            throw new DomainException('Encryption key update required before sending new messages.');
        }

        $participantIds = $conversation->activeParticipants()->pluck('user_id');
        $expectedDeviceIds = ChatE2eeDevice::query()
            ->whereIn('user_id', $participantIds)
            ->whereNotNull('trusted_at')
            ->whereNull('revoked_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();
        $provisionedDeviceIds = ChatE2eeConversationKey::query()
            ->where('conversation_id', $conversation->id)
            ->where('key_version', $conversation->current_key_version)
            ->pluck('device_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        if ($expectedDeviceIds->isEmpty() || $expectedDeviceIds->all() !== $provisionedDeviceIds->all()) {
            throw new DomainException('Encrypted sending is paused until the conversation key is safely rotated.');
        }
    }

    private function validateMessageEnvelope(
        ChatConversation $conversation,
        string $clientMessageUuid,
        string $encryptedPayload,
        int $encryptionVersion,
        int $keyVersion,
    ): array {
        if (! Str::isUuid($clientMessageUuid)) {
            throw new DomainException('A valid client message UUID is required.');
        }

        $envelope = $this->envelopes->validate($encryptedPayload);
        if ($encryptionVersion !== E2eeEnvelopeValidator::FORMAT_VERSION
            || $keyVersion !== $conversation->current_key_version
            || $envelope['v'] !== $encryptionVersion
            || $envelope['key_version'] !== $keyVersion) {
            throw new DomainException('The encrypted message key or format version is not current.');
        }

        return $envelope;
    }
}
