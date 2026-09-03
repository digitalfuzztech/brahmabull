<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class E2eeDowngradeService
{
    public function __construct(private readonly E2eeAuthorizationService $authorization) {}

    public function requestDisable(ChatConversation $conversation, User $requester): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $requester): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->assertEnabledDirect($locked, $requester);

            if ($locked->e2ee_disable_requested_by !== null) {
                if ((int) $locked->e2ee_disable_requested_by === $requester->id) {
                    return $locked;
                }

                throw new DomainException('The other participant already has a pending encryption-disable request.');
            }

            $locked->update([
                'e2ee_disable_requested_by' => $requester->id,
                'e2ee_disable_requested_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function keepEncryption(ChatConversation $conversation, User $responder): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $responder): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->assertOtherParticipantMayRespond($locked, $responder);
            $locked->update([
                'e2ee_disable_requested_by' => null,
                'e2ee_disable_requested_at' => null,
            ]);

            return $locked->fresh();
        });
    }

    public function approveDisable(ChatConversation $conversation, User $approver): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $approver): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->assertOtherParticipantMayRespond($locked, $approver);
            $disabledAt = now();

            $locked->update([
                'encryption_mode' => null,
                'e2ee_enabled_at' => null,
                'e2ee_rotation_required_at' => null,
                'e2ee_disable_requested_by' => null,
                'e2ee_disable_requested_at' => null,
                'e2ee_disabled_at' => $disabledAt,
                'last_message_at' => $disabledAt,
            ]);

            ChatMessage::create([
                'conversation_id' => $locked->id,
                'sender_type' => 'system',
                'message_type' => 'system',
                'body' => 'End-to-end encryption was disabled for new messages.',
                'metadata' => ['e2ee_disabled_boundary' => true],
                'read_by_staff_at' => $disabledAt,
            ]);

            return $locked->fresh();
        });
    }

    private function assertEnabledDirect(ChatConversation $conversation, User $actor): void
    {
        $this->authorization->assertDirectParticipant($conversation, $actor);
        if ($conversation->encryption_mode !== 'e2ee_v1' || ! $conversation->current_key_version) {
            throw new AuthorizationException('This direct conversation is not E2EE-enabled.');
        }
    }

    private function assertOtherParticipantMayRespond(ChatConversation $conversation, User $responder): void
    {
        $this->assertEnabledDirect($conversation, $responder);
        if ($conversation->e2ee_disable_requested_by === null) {
            throw new DomainException('There is no pending encryption-disable request.');
        }
        if ((int) $conversation->e2ee_disable_requested_by === $responder->id) {
            throw new AuthorizationException('The requester cannot approve or reject their own encryption downgrade.');
        }
    }
}
