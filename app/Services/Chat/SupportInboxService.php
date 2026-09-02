<?php

namespace App\Services\Chat;

use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSupportEvent;
use App\Models\Deposit;
use App\Models\GameAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupportInboxService
{
    public function __construct(
        private readonly ChatAuthorizationService $authorization,
        private readonly ChatMessageService $messages,
    ) {}

    public function conversations(User $staff, string $filter, string $search = ''): Collection
    {
        $this->assertStaff($staff);
        $filter = $this->normalizeFilter($staff, $filter);

        $query = ChatConversation::query()
            ->where('conversation_type', 'support')
            ->with([
                'player:id,name,username,brahma_balance',
                'assignedStaff:id,name,username',
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_type', 'player')
                    ->whereNull('read_by_staff_at'),
            ])
            ->addSelect([
                'latest_message_preview' => ChatMessage::query()
                    ->select('body')
                    ->whereColumn('conversation_id', 'chat_conversations.id')
                    ->latest('id')
                    ->limit(1),
            ]);

        if ($staff->hasRole('agent')) {
            $query->where(fn ($query) => $query
                ->where('status', '!=', 'resolved')
                ->orWhere('assigned_to', $staff->id));
        }

        $this->applyFilter($query, $staff, $filter);

        if ($search = trim($search)) {
            $query->where(function ($query) use ($search): void {
                $query->where('reference', 'like', '%'.$search.'%')
                    ->orWhereHas('player', function ($query) use ($search): void {
                        $query->where('name', 'like', '%'.$search.'%')
                            ->orWhere('username', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query
            ->orderByDesc(DB::raw('COALESCE(last_message_at, created_at)'))
            ->limit(75)
            ->get();
    }

    public function selectConversation(int $conversationId, User $staff): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->where('conversation_type', 'support')
            ->with(['player:id,name,username,brahma_balance', 'assignedStaff:id,name,username'])
            ->findOrFail($conversationId);

        $this->authorization->assertCanView($conversation, $staff);
        $this->messages->markMessagesReadByStaff($conversation, $staff);

        return $conversation->fresh(['player:id,name,username,brahma_balance', 'assignedStaff:id,name,username']);
    }

    public function timeline(ChatConversation $conversation, User $staff, int $limit = 100): array
    {
        $this->authorization->assertCanView($conversation, $staff);

        return $conversation->messages()
            ->with('sender:id,name,username')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => $this->staffMessage($message, $conversation))
            ->all();
    }

    public function playerContext(ChatConversation $conversation, User $staff): array
    {
        $this->authorization->assertCanView($conversation, $staff);
        $player = $conversation->player()->firstOrFail();

        return [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'username' => $player->username,
                'brahma_balance' => number_format((float) $player->brahma_balance, 2),
            ],
            'brahma_plays' => BrahmaPlayRequest::query()
                ->where('user_id', $player->id)->where('status', 'pending')
                ->with('game:id,name')->latest()->limit(10)->get()
                ->map(fn ($request) => [
                    'reference' => $request->reference,
                    'game' => $request->game?->name ?? 'Game',
                    'amount' => number_format((float) $request->points_to_load, 2),
                ])->all(),
            'deposits' => Deposit::query()
                ->where('user_id', $player->id)->where('status', 'pending')
                ->with('game:id,name')->latest()->limit(10)->get()
                ->map(fn ($deposit) => [
                    'reference' => $deposit->reference,
                    'game' => $deposit->game?->name ?? 'Game',
                    'amount' => number_format((float) $deposit->amount, 2),
                ])->all(),
            'brahma_deposits' => BrahmaDeposit::query()
                ->where('user_id', $player->id)->where('status', 'pending')
                ->latest()->limit(10)->get(['id', 'reference', 'amount'])
                ->map(fn ($deposit) => [
                    'reference' => $deposit->reference,
                    'amount' => number_format((float) $deposit->amount, 2),
                ])->all(),
            'cashouts' => Cashout::query()
                ->where('user_id', $player->id)->where('status', 'pending')
                ->with('game:id,name')->latest()->limit(10)->get()
                ->map(fn ($cashout) => [
                    'reference' => $cashout->reference,
                    'game' => $cashout->game?->name ?? 'Game',
                    'amount' => number_format((float) $cashout->amount, 2),
                ])->all(),
            'game_accounts' => GameAccount::query()
                ->where('user_id', $player->id)->with('game:id,name')->latest()->limit(5)->get()
                ->map(fn ($account) => [
                    'game' => $account->game?->name ?? 'Game',
                    'username' => $account->game_username,
                ])->all(),
        ];
    }

    public function supportEvents(ChatConversation $conversation, User $staff): array
    {
        $this->authorization->assertCanView($conversation, $staff);

        $events = $conversation->supportEvents()
            ->where('player_id', $conversation->player_id)
            ->latest('id')
            ->get();
        $plays = BrahmaPlayRequest::with('game:id,name')
            ->where('user_id', $conversation->player_id)
            ->whereIn('id', $events->where('related_type', 'brahma_play_request')->pluck('related_id'))
            ->get()->keyBy('id');
        $deposits = Deposit::with('game:id,name')
            ->where('user_id', $conversation->player_id)
            ->whereIn('id', $events->where('related_type', 'deposit')->pluck('related_id'))
            ->get()->keyBy('id');
        $brahmaDeposits = BrahmaDeposit::where('user_id', $conversation->player_id)
            ->whereIn('id', $events->where('related_type', 'brahma_deposit')->pluck('related_id'))
            ->get()->keyBy('id');
        $cashouts = Cashout::with('game:id,name')
            ->where('user_id', $conversation->player_id)
            ->whereIn('id', $events->where('related_type', 'cashout')->pluck('related_id'))
            ->get()->keyBy('id');

        return $events
            ->map(fn (ChatSupportEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'label' => match ($event->event_type) {
                    'play_reminder' => 'Play request reminder',
                    'deposit_reminder' => 'Game deposit reminder',
                    'brahma_deposit_reminder' => 'Brahma deposit reminder',
                    'cashout_reminder' => 'Cashout reminder',
                    default => 'Human support requested',
                },
                'summary' => match ($event->related_type) {
                    'brahma_play_request' => ($request = $plays->get($event->related_id))
                        ? ($request->game?->name ?? 'Game').' · '.number_format((float) $request->points_to_load, 2).' points'
                        : null,
                    'deposit' => ($deposit = $deposits->get($event->related_id))
                        ? ($deposit->game?->name ?? 'Game').' · $'.number_format((float) $deposit->amount, 2)
                        : null,
                    'brahma_deposit' => ($deposit = $brahmaDeposits->get($event->related_id))
                        ? 'Brahma Deposit · $'.number_format((float) $deposit->amount, 2)
                        : null,
                    'cashout' => ($cashout = $cashouts->get($event->related_id))
                        ? ($cashout->game?->name ?? 'Cashout').' · $'.number_format((float) $cashout->amount, 2)
                        : null,
                    default => null,
                },
                'status' => $event->status,
                'created_at' => $event->created_at?->toISOString(),
            ])->all();
    }

    public function activeAgents(User $admin): Collection
    {
        if (! $admin->hasRole('admin')) {
            throw new AuthorizationException('Only administrators may select agents.');
        }

        return User::role('agent')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']);
    }

    public function activeAgent(User $admin, int $agentId): User
    {
        if (! $admin->hasRole('admin')) {
            throw new AuthorizationException('Only administrators may select agents.');
        }

        $agent = User::role('agent')->where('is_active', true)->find($agentId);

        if (! $agent) {
            throw new AuthorizationException('The selected user is not an active agent.');
        }

        return $agent;
    }

    public function markEventHandled(ChatSupportEvent $event, ChatConversation $conversation, User $staff): ChatSupportEvent
    {
        return DB::transaction(function () use ($event, $conversation, $staff): ChatSupportEvent {
            $lockedConversation = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertCanReply($lockedConversation, $staff);
            $lockedEvent = ChatSupportEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($lockedConversation->conversation_type !== 'support'
                || $lockedEvent->conversation_id !== $lockedConversation->id
                || $lockedEvent->player_id !== $lockedConversation->player_id) {
                throw new AuthorizationException('This support event does not belong to the conversation.');
            }

            if ($lockedEvent->status === 'pending') {
                $lockedEvent->update([
                    'status' => 'handled',
                    'handled_by' => $staff->id,
                    'handled_at' => now(),
                ]);
            }

            return $lockedEvent->fresh();
        });
    }

    private function staffMessage(ChatMessage $message, ChatConversation $conversation): array
    {
        $displayName = match ($message->sender_type) {
            'player' => 'Player — '.$conversation->player->name,
            'admin' => 'Admin — '.($message->sender?->name ?? 'Former staff'),
            'agent' => 'Agent — '.($message->sender?->name ?? 'Former staff'),
            'bot' => 'Bot',
            default => 'System',
        };

        return [
            'id' => $message->id,
            'body' => $message->body,
            'message_type' => $message->message_type,
            'sender_type' => $message->sender_type,
            'display_name' => $displayName,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function normalizeFilter(User $staff, string $filter): string
    {
        $allowed = $staff->hasRole('admin')
            ? ['all', 'open', 'waiting', 'unassigned', 'mine', 'assigned', 'resolved']
            : ['all', 'open', 'waiting', 'mine', 'resolved'];

        return in_array($filter, $allowed, true) ? $filter : 'waiting';
    }

    private function applyFilter($query, User $staff, string $filter): void
    {
        match ($filter) {
            'open' => $query->whereIn('status', ['bot', 'waiting'])->whereNull('assigned_to'),
            'waiting' => $query->where('status', 'waiting'),
            'unassigned' => $query->where('status', 'waiting')->whereNull('assigned_to'),
            'mine' => $query->where('assigned_to', $staff->id)->where('status', '!=', 'resolved'),
            'assigned' => $query->whereNotNull('assigned_to')->where('status', '!=', 'resolved'),
            'resolved' => $query->where('status', 'resolved'),
            default => null,
        };
    }

    private function assertStaff(User $staff): void
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only support staff may access the Inbox.');
        }
    }
}
