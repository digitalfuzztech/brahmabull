<?php

namespace App\Services\Chat;

use App\Exceptions\ConversationAlreadyHandledException;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatSupportEvent;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(private readonly ChatAuthorizationService $authorization) {}

    public function getOrCreatePlayerConversation(User $player): ChatConversation
    {
        if (! $player->hasRole('player')) {
            throw new AuthorizationException('Only players may start player conversations.');
        }

        return DB::transaction(function () use ($player): ChatConversation {
            User::whereKey($player->id)->lockForUpdate()->firstOrFail();

            $conversation = ChatConversation::where('player_id', $player->id)
                ->where('conversation_type', 'support')
                ->whereIn('status', ChatConversation::OPEN_STATUSES)
                ->latest('id')
                ->first();

            return $conversation ?? ChatConversation::create([
                'conversation_type' => 'support',
                'player_id' => $player->id,
                'status' => 'bot',
            ]);
        });
    }

    public function viewConversation(ChatConversation $conversation, User $actor): ChatConversation
    {
        $this->authorization->assertCanView($conversation, $actor);

        return $conversation;
    }

    public function requestHumanSupport(ChatConversation $conversation, User $player): ChatConversation
    {
        return $this->requestHumanSupportWithEvent($conversation, $player)['conversation'];
    }

    public function requestHumanSupportWithEvent(ChatConversation $conversation, User $player): array
    {
        return DB::transaction(function () use ($conversation, $player): array {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertPlayerOwns($locked, $player);

            $event = null;
            $created = false;

            if ($locked->status === 'bot') {
                $locked->update([
                    'status' => 'waiting',
                    'human_requested_at' => now(),
                ]);

                $event = ChatSupportEvent::create([
                    'conversation_id' => $locked->id,
                    'player_id' => $player->id,
                    'event_type' => 'human_support_requested',
                    'status' => 'pending',
                ]);
                $created = true;
            } else {
                $event = ChatSupportEvent::where('conversation_id', $locked->id)
                    ->where('event_type', 'human_support_requested')
                    ->latest('id')
                    ->first();
            }

            return [
                'conversation' => $locked->fresh(),
                'event' => $event,
                'created' => $created,
            ];
        });
    }

    public function takeConversation(ChatConversation $conversation, User $staff): ChatConversation
    {
        $this->authorization->assertCanTake($staff);

        return DB::transaction(function () use ($conversation, $staff): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            if ($locked->conversation_type !== 'support') {
                throw new AuthorizationException('Only support conversations can be taken.');
            }

            if (! in_array($locked->status, ['bot', 'waiting'], true) || $locked->assigned_to !== null) {
                throw new ConversationAlreadyHandledException;
            }

            $locked->update([
                'status' => 'active',
                'assigned_to' => $staff->id,
                'assigned_by' => $staff->id,
                'assigned_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function assignConversation(ChatConversation $conversation, User $admin, User $agent): ChatConversation
    {
        $this->authorization->assertCanAssign($admin, $agent);

        return DB::transaction(function () use ($conversation, $admin, $agent): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            if ($locked->conversation_type !== 'support') {
                throw new AuthorizationException('Only support conversations can be assigned.');
            }

            if (! in_array($locked->status, ['bot', 'waiting'], true) || $locked->assigned_to !== null) {
                throw new ConversationAlreadyHandledException;
            }

            $locked->update([
                'status' => 'active',
                'assigned_to' => $agent->id,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function reassignConversation(ChatConversation $conversation, User $admin, User $agent): ChatConversation
    {
        $this->authorization->assertCanReassign($admin, $agent);

        return DB::transaction(function () use ($conversation, $admin, $agent): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            if ($locked->conversation_type !== 'support') {
                throw new AuthorizationException('Only support conversations can be reassigned.');
            }

            if ($locked->status !== 'active' || $locked->assigned_to === null) {
                throw new DomainException('Only an active assigned conversation may be reassigned.');
            }

            $locked->update([
                'assigned_to' => $agent->id,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function resolveConversation(ChatConversation $conversation, User $staff): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $staff): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanResolve($locked, $staff);

            $locked->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $staff->id,
            ]);

            return $locked->fresh();
        });
    }

    public function getOrCreateDirectConversation(User $first, User $second): ChatConversation
    {
        // Direct membership is the complete access boundary so a later E2EE phase can exclude non-participants.
        $this->assertValidDirectPair($first, $second);
        $userIds = collect([$first->id, $second->id])->sort()->values();
        $directKey = $userIds->implode(':');

        try {
            return DB::transaction(function () use ($first, $userIds, $directKey): ChatConversation {
                User::whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get();

                $existing = ChatConversation::where('conversation_type', 'internal_direct')
                    ->where('direct_key', $directKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->update(['is_archived' => false]);
                    $this->restoreDirectParticipants($existing, $userIds->all());

                    return $existing->fresh();
                }

                $conversation = ChatConversation::create([
                    'conversation_type' => 'internal_direct',
                    'player_id' => null,
                    'status' => null,
                    'name' => null,
                    'created_by' => $first->id,
                    'direct_key' => $directKey,
                ]);

                foreach ($userIds as $userId) {
                    ChatConversationParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'participant_role' => 'member',
                        'joined_at' => now(),
                    ]);
                }

                return $conversation->fresh('participants');
            });
        } catch (QueryException $exception) {
            $existing = ChatConversation::where('conversation_type', 'internal_direct')
                ->where('direct_key', $directKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function createGroupConversation(User $creator, string $name, array $agents): ChatConversation
    {
        if (! $creator->hasRole('agent') || blank(trim($name))) {
            throw new AuthorizationException('Only agents may create a named internal group.');
        }

        $agentIds = collect($agents)
            ->map(fn ($agent) => $agent instanceof User ? $agent->id : (int) $agent)
            ->push($creator->id)
            ->unique()
            ->sort()
            ->values();

        if ($agentIds->count() < 3) {
            throw new DomainException('An internal group requires at least three agents.');
        }

        return DB::transaction(function () use ($creator, $name, $agentIds): ChatConversation {
            $lockedAgents = User::whereIn('id', $agentIds)->orderBy('id')->lockForUpdate()->get();

            if ($lockedAgents->count() !== $agentIds->count()
                || $lockedAgents->contains(fn (User $user) => ! $user->hasRole('agent'))) {
                throw new AuthorizationException('Internal groups may contain agents only.');
            }

            $conversation = ChatConversation::create([
                'conversation_type' => 'internal_group',
                'player_id' => null,
                'status' => null,
                'name' => trim($name),
                'created_by' => $creator->id,
            ]);

            foreach ($agentIds as $agentId) {
                ChatConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $agentId,
                    'participant_role' => $agentId === $creator->id ? 'owner' : 'member',
                    'joined_at' => now(),
                ]);
            }

            return $conversation->fresh('participants');
        });
    }

    public function addGroupParticipant(ChatConversation $conversation, User $owner, User $agent): ChatConversationParticipant
    {
        if (! $agent->hasRole('agent')) {
            throw new AuthorizationException('Only agents may join an internal group.');
        }

        return DB::transaction(function () use ($conversation, $owner, $agent): ChatConversationParticipant {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanManageGroup($locked, $owner);
            $participant = ChatConversationParticipant::where('conversation_id', $locked->id)
                ->where('user_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if ($participant && $participant->left_at === null) {
                throw new DomainException('This agent is already an active group participant.');
            }

            if ($participant) {
                $participant->update([
                    'participant_role' => 'member',
                    'joined_at' => now(),
                    'left_at' => null,
                    'last_read_at' => null,
                ]);

                return $participant->fresh();
            }

            return ChatConversationParticipant::create([
                'conversation_id' => $locked->id,
                'user_id' => $agent->id,
                'participant_role' => 'member',
                'joined_at' => now(),
            ]);
        });
    }

    public function removeGroupParticipant(ChatConversation $conversation, User $owner, User $agent): void
    {
        DB::transaction(function () use ($conversation, $owner, $agent): void {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanManageGroup($locked, $owner);

            if ($owner->id === $agent->id) {
                throw new DomainException('The owner must use leaveGroup so ownership can be transferred.');
            }

            $participant = ChatConversationParticipant::where('conversation_id', $locked->id)
                ->where('user_id', $agent->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();
            $participant->update(['left_at' => now()]);
        });
    }

    public function leaveGroup(ChatConversation $conversation, User $agent): void
    {
        DB::transaction(function () use ($conversation, $agent): void {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            if ($locked->conversation_type !== 'internal_group') {
                throw new AuthorizationException('Only internal groups can be left.');
            }

            $participant = ChatConversationParticipant::where('conversation_id', $locked->id)
                ->where('user_id', $agent->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();

            if ($participant->participant_role === 'owner') {
                $successor = ChatConversationParticipant::where('conversation_id', $locked->id)
                    ->where('user_id', '!=', $agent->id)
                    ->whereNull('left_at')
                    ->oldest('joined_at')
                    ->lockForUpdate()
                    ->first();

                if ($successor) {
                    $successor->update(['participant_role' => 'owner']);
                } else {
                    $locked->update(['is_archived' => true]);
                }
            }

            $participant->update(['left_at' => now()]);
        });
    }

    public function renameGroup(ChatConversation $conversation, User $owner, string $name): ChatConversation
    {
        if (blank(trim($name))) {
            throw new DomainException('A group name is required.');
        }

        return DB::transaction(function () use ($conversation, $owner, $name): ChatConversation {
            $locked = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanManageGroup($locked, $owner);
            $locked->update(['name' => trim($name)]);

            return $locked->fresh();
        });
    }

    public function markInternalConversationRead(ChatConversation $conversation, User $participant): void
    {
        $this->authorization->assertCanViewInternal($conversation, $participant);

        DB::transaction(function () use ($conversation, $participant): void {
            $participantRow = ChatConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $participant->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if (! $participantRow) {
                throw new AuthorizationException('Observers do not have participant read state.');
            }

            $participantRow->update(['last_read_at' => now()]);
        });
    }

    public function internalUnreadCount(ChatConversation $conversation, User $participant): int
    {
        $this->authorization->assertCanViewInternal($conversation, $participant);

        return $this->internalUnreadQuery($participant, $conversation->conversation_type)
            ->where('chat_messages.conversation_id', $conversation->id)
            ->count();
    }

    public function internalDirectUnreadCount(User $participant): int
    {
        return $this->internalUnreadQuery($participant, 'internal_direct')->count();
    }

    public function internalGroupUnreadCount(User $participant): int
    {
        return $this->internalUnreadQuery($participant, 'internal_group')->count();
    }

    public function internalChannelUnreadCount(User $participant): int
    {
        return $this->internalUnreadQuery($participant, 'internal_channel')->count();
    }

    private function internalUnreadQuery(User $participant, string $conversationType)
    {
        return DB::table('chat_messages')
            ->join('chat_conversations', 'chat_conversations.id', '=', 'chat_messages.conversation_id')
            ->join('chat_conversation_participants', function ($join) use ($participant): void {
                $join->on('chat_conversation_participants.conversation_id', '=', 'chat_messages.conversation_id')
                    ->where('chat_conversation_participants.user_id', '=', $participant->id)
                    ->whereNull('chat_conversation_participants.left_at');
            })
            ->where('chat_conversations.conversation_type', $conversationType)
            ->where('chat_messages.sender_id', '!=', $participant->id)
            ->whereColumn('chat_messages.created_at', '>=', 'chat_conversation_participants.joined_at')
            ->where(function ($query): void {
                $query->whereNull('chat_conversation_participants.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_conversation_participants.last_read_at');
            });
    }

    private function assertValidDirectPair(User $first, User $second): void
    {
        if ($first->id === $second->id
            || ! $first->hasAnyRole(['admin', 'agent'])
            || ! $second->hasAnyRole(['admin', 'agent'])
            || (! $first->hasRole('agent') && ! $second->hasRole('agent'))) {
            throw new AuthorizationException('Direct chats require two distinct staff users and at least one agent.');
        }
    }

    private function restoreDirectParticipants(ChatConversation $conversation, array $userIds): void
    {
        $expectedUserIds = collect($userIds)->map(fn ($id) => (int) $id)->sort()->values();

        if ($conversation->conversation_type !== 'internal_direct'
            || $conversation->direct_key !== $expectedUserIds->implode(':')) {
            throw new DomainException('The existing direct conversation does not match this staff pair.');
        }

        $activeUserIds = ChatConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('left_at')
            ->lockForUpdate()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        if ($activeUserIds->diff($expectedUserIds)->isNotEmpty()) {
            throw new DomainException('The existing direct conversation has unexpected participants.');
        }

        foreach ($expectedUserIds as $userId) {
            $participant = ChatConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($participant && $participant->left_at === null) {
                continue;
            }

            if ($participant) {
                $participant->update([
                    'participant_role' => 'member',
                    'joined_at' => now(),
                    'left_at' => null,
                    'last_read_at' => null,
                ]);
            } else {
                ChatConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'participant_role' => 'member',
                    'joined_at' => now(),
                ]);
            }
        }
    }
}
