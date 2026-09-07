<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ChatAuthorizationService
{
    public function assertPlayerOwns(ChatConversation $conversation, User $player): void
    {
        if ($conversation->conversation_type !== 'support'
            || ! $player->hasRole('player')
            || $conversation->player_id !== $player->id
            || ! in_array($conversation->status, ChatConversation::OPEN_STATUSES, true)) {
            throw new AuthorizationException('You cannot access this conversation.');
        }
    }

    public function assertCanView(ChatConversation $conversation, User $actor): void
    {
        if ($conversation->conversation_type !== 'support') {
            $this->assertCanViewInternal($conversation, $actor);

            return;
        }

        if ($actor->hasRole('admin')) {
            return;
        }

        if ($actor->hasRole('player')
            && $conversation->player_id === $actor->id
            && in_array($conversation->status, ChatConversation::OPEN_STATUSES, true)) {
            return;
        }

        if ($actor->hasRole('agent')) {
            if ($conversation->status !== 'resolved' || $conversation->assigned_to === $actor->id) {
                return;
            }
        }

        throw new AuthorizationException('You cannot access this conversation.');
    }

    public function assertCanTake(User $staff): void
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only support staff may take a conversation.');
        }
    }

    public function assertCanAssign(User $admin, User $agent): void
    {
        if (! $admin->hasRole('admin') || ! $agent->hasRole('agent')) {
            throw new AuthorizationException('Only administrators may assign conversations to agents.');
        }
    }

    public function assertCanReassign(User $admin, User $agent): void
    {
        $this->assertCanAssign($admin, $agent);
    }

    public function assertCanReply(ChatConversation $conversation, User $staff): void
    {
        if ($conversation->conversation_type !== 'support') {
            throw new AuthorizationException('Support reply authorization cannot be used for internal chat.');
        }

        if ($staff->hasRole('admin') && $conversation->status !== 'resolved') {
            return;
        }

        if ($staff->hasRole('agent')
            && $conversation->status === 'active'
            && $conversation->assigned_to === $staff->id) {
            return;
        }

        if ($staff->hasRole('agent')
            && in_array($conversation->status, ['bot', 'waiting'], true)
            && $conversation->assigned_to === null) {
            return;
        }

        throw new AuthorizationException('You cannot reply to this conversation.');
    }

    public function assertCanResolve(ChatConversation $conversation, User $staff): void
    {
        if ($conversation->conversation_type !== 'support') {
            throw new AuthorizationException('Only support conversations use resolution.');
        }

        if ($staff->hasRole('admin') && $conversation->status !== 'resolved') {
            return;
        }

        if ($staff->hasRole('agent')
            && $conversation->status === 'active'
            && $conversation->assigned_to === $staff->id) {
            return;
        }

        throw new AuthorizationException('You cannot resolve this conversation.');
    }

    public function assertCanManageRules(User $actor): void
    {
        $this->assertCanManageChatSettings($actor);
    }

    public function assertCanManageChatSettings(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Only administrators may manage chat settings.');
        }
    }

    public function assertCanViewInternal(ChatConversation $conversation, User $actor): void
    {
        $this->assertInternalConversation($conversation);

        if ($conversation->conversation_type === 'internal_direct'
            && $this->isActiveParticipant($conversation, $actor)) {
            return;
        }

        if ($conversation->conversation_type === 'internal_group'
            && $actor->hasRole('agent')
            && $this->isActiveParticipant($conversation, $actor)) {
            return;
        }

        if ($conversation->conversation_type === 'internal_channel'
            && $actor->hasAnyRole(['admin', 'agent'])
            && ! $conversation->is_archived
            && $this->isActiveParticipant($conversation, $actor)) {
            return;
        }

        if ($this->canReadInternalGroupAsAdmin($conversation, $actor)) {
            return;
        }

        throw new AuthorizationException('You cannot access this internal conversation.');
    }

    public function assertCanSendInternal(ChatConversation $conversation, User $actor): void
    {
        $this->assertInternalConversation($conversation);

        if ($conversation->is_archived) {
            throw new AuthorizationException('Archived conversations are read-only.');
        }

        if ($conversation->conversation_type === 'internal_direct'
            && $this->isActiveParticipant($conversation, $actor)) {
            return;
        }

        if ($conversation->conversation_type === 'internal_group'
            && $actor->hasRole('agent')
            && $this->isActiveParticipant($conversation, $actor)) {
            return;
        }

        if ($conversation->conversation_type === 'internal_channel' && $this->isActiveParticipant($conversation, $actor)) {
            if ($actor->hasRole('admin')) {
                return;
            }
            $mode = $conversation->channel_mode ?? 'read_only';
            if ($actor->hasRole('agent') && ($mode === 'open' || ($mode === 'restricted'
                && $conversation->participants()->where('user_id', $actor->id)->where('channel_can_post', true)
                    ->whereNull('channel_blocked_at')->exists()))) {
                return;
            }
        }

        throw new AuthorizationException('You cannot send messages to this internal conversation.');
    }

    public function canSendInternal(ChatConversation $conversation, User $actor): bool
    {
        try {
            $this->assertCanSendInternal($conversation, $actor);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function assertCanManageGroup(ChatConversation $conversation, User $actor): void
    {
        if ($conversation->conversation_type !== 'internal_group'
            || ! $actor->hasRole('agent')
            || ! $conversation->participants()
                ->where('user_id', $actor->id)
                ->where('participant_role', 'owner')
                ->whereNull('left_at')
                ->exists()) {
            throw new AuthorizationException('Only the active group owner may manage this group.');
        }
    }

    public function assertCanReact(ChatConversation $conversation, User $actor): void
    {
        if ($conversation->conversation_type === 'internal_channel'
            && $conversation->channel_key === BrahmaNoticeboardService::CHANNEL_KEY) {
            throw new AuthorizationException('Noticeboard reactions are disabled.');
        }

        if ($conversation->conversation_type === 'internal_direct' && $conversation->encryption_mode === 'e2ee_v1') {
            throw new AuthorizationException('Encrypted direct-message reactions must use the browser ciphertext path.');
        }

        $this->assertCanSendInternal($conversation, $actor);
    }

    public function assertCanCreateAttachment(ChatConversation $conversation, User $actor): void
    {
        if ($conversation->conversation_type === 'internal_direct' && $conversation->encryption_mode === 'e2ee_v1') {
            throw new AuthorizationException('Encrypted direct-message attachments must use the browser ciphertext path.');
        }

        $this->assertCanSendInternal($conversation, $actor);
    }

    public function assertCanEditInternalMessage(ChatMessage $message, User $actor): void
    {
        $conversation = $message->conversation()->firstOrFail();
        $this->assertCanSendInternal($conversation, $actor);

        if ($message->sender_id !== $actor->id
            || $message->deleted_at !== null
            || (blank($message->body) && blank($message->encrypted_payload))) {
            throw new AuthorizationException('Only the original sender may edit this internal message.');
        }
    }

    public function assertCanDeleteInternalMessage(ChatMessage $message, User $actor): void
    {
        $conversation = $message->conversation()->firstOrFail();
        $this->assertCanSendInternal($conversation, $actor);

        if ($message->sender_id !== $actor->id || $message->deleted_at !== null) {
            throw new AuthorizationException('Only the original sender may delete this internal message.');
        }
    }

    public function canReadInternalGroupAsAdmin(ChatConversation $conversation, User $actor): bool
    {
        if (! $actor->hasRole('admin') || $conversation->conversation_type !== 'internal_group') {
            return false;
        }

        // Group encryption must preserve this explicit oversight path in a future crypto phase.
        return $conversation->participants()
            ->whereNull('left_at')
            ->whereHas('user.roles', fn ($query) => $query->where('name', 'agent'))
            ->count() >= 3;
    }

    public function isActiveParticipant(ChatConversation $conversation, User $actor): bool
    {
        return $conversation->participants()
            ->where('user_id', $actor->id)
            ->whereNull('left_at')
            ->exists();
    }

    private function assertInternalConversation(ChatConversation $conversation): void
    {
        if (! in_array($conversation->conversation_type, ['internal_direct', 'internal_group', 'internal_channel'], true)) {
            throw new AuthorizationException('This operation is available only for internal conversations.');
        }
    }
}
