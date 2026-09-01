<?php

namespace App\Services\Chat;

use App\Exceptions\InvalidChatbotActionException;
use App\Exceptions\ReminderThrottledException;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\ChatConversation;
use App\Models\ChatSupportEvent;
use App\Models\Deposit;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatbotActionService
{
    public const ACTIONS = [
        'none',
        'show_main_menu',
        'show_pending_plays',
        'show_pending_deposits',
        'show_pending_brahma_deposits',
        'show_pending_cashouts',
        'show_brahma_balance',
        'remind_play_request',
        'remind_deposit',
        'remind_brahma_deposit',
        'remind_cashout',
        'request_human',
    ];

    public function __construct(
        private readonly ChatAuthorizationService $authorization,
        private readonly ConversationService $conversations,
    ) {}

    public function assertSupportedAction(?string $actionType): void
    {
        $actionType ??= 'none';

        if (! in_array($actionType, self::ACTIONS, true)) {
            throw new InvalidChatbotActionException("Unsupported chatbot action [{$actionType}].");
        }
    }

    public function execute(
        string $actionType,
        ChatConversation $conversation,
        User $player,
        array $config = [],
    ): mixed {
        $this->assertSupportedAction($actionType);
        $this->authorization->assertPlayerOwns($conversation, $player);

        return match ($actionType) {
            'none' => null,
            'show_main_menu' => $this->mainMenu(),
            'show_pending_plays' => $this->pendingPlayRequests($player),
            'show_pending_deposits' => $this->pendingNormalDeposits($player),
            'show_pending_brahma_deposits' => $this->pendingBrahmaDeposits($player),
            'show_pending_cashouts' => $this->pendingCashouts($player),
            'show_brahma_balance' => $this->currentBrahmaBalance($player),
            'remind_play_request' => $this->createReminder(
                $conversation,
                $player,
                'play_reminder',
                $config['related_type'] ?? 'brahma_play_request',
                (int) ($config['related_id'] ?? 0),
            ),
            'remind_deposit' => $this->createReminder($conversation, $player, 'deposit_reminder', 'deposit', (int) ($config['related_id'] ?? 0)),
            'remind_brahma_deposit' => $this->createReminder($conversation, $player, 'brahma_deposit_reminder', 'brahma_deposit', (int) ($config['related_id'] ?? 0)),
            'remind_cashout' => $this->createReminder($conversation, $player, 'cashout_reminder', 'cashout', (int) ($config['related_id'] ?? 0)),
            'request_human' => $this->conversations->requestHumanSupport($conversation, $player),
        };
    }

    public function pendingPlayRequests(User $player): Collection
    {
        $this->assertPlayer($player);

        $brahmaPlays = BrahmaPlayRequest::with('game:id,name')
            ->where('user_id', $player->id)
            ->where('status', 'pending')
            ->get()
            ->map(fn (BrahmaPlayRequest $request) => [
                'source' => 'brahma_play_request',
                'request_id' => $request->id,
                'game' => $request->game?->name,
                'requested_points' => $request->points_to_load,
                'amount' => null,
                'reference' => $request->reference,
                'status' => $request->status,
                'created_at' => $request->created_at?->toISOString(),
            ]);

        $normalPlays = Deposit::with('game:id,name')
            ->where('user_id', $player->id)
            ->where('status', 'pending')
            ->get()
            ->map(fn (Deposit $deposit) => [
                'source' => 'deposit',
                'request_id' => $deposit->id,
                'game' => $deposit->game?->name,
                'requested_points' => null,
                'amount' => $deposit->amount,
                'reference' => $deposit->reference,
                'status' => $deposit->status,
                'created_at' => $deposit->created_at?->toISOString(),
            ]);

        return $brahmaPlays
            ->concat($normalPlays)
            ->sortByDesc('created_at')
            ->values();
    }

    public function pendingNormalDeposits(User $player): Collection
    {
        $this->assertPlayer($player);

        return Deposit::with('game:id,name')
            ->where('user_id', $player->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (Deposit $deposit) => [
                'id' => $deposit->id,
                'game' => $deposit->game?->name,
                'amount' => $deposit->amount,
                'reference' => $deposit->reference,
                'status' => $deposit->status,
            ]);
    }

    public function pendingBrahmaDeposits(User $player): Collection
    {
        $this->assertPlayer($player);

        return BrahmaDeposit::where('user_id', $player->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (BrahmaDeposit $deposit) => [
                'id' => $deposit->id,
                'amount' => $deposit->amount,
                'reference' => $deposit->reference,
                'status' => $deposit->status,
            ]);
    }

    public function pendingCashouts(User $player): Collection
    {
        $this->assertPlayer($player);

        return Cashout::with('game:id,name')
            ->where('user_id', $player->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (Cashout $cashout) => [
                'id' => $cashout->id,
                'game' => $cashout->game?->name,
                'amount' => $cashout->amount,
                'reference' => $cashout->reference,
                'status' => $cashout->status,
            ]);
    }

    public function currentBrahmaBalance(User $player): string
    {
        $this->assertPlayer($player);

        $balance = User::whereKey($player->id)->value('brahma_balance');

        return number_format((float) $balance, 2, '.', '');
    }

    public function createReminder(
        ChatConversation $conversation,
        User $player,
        string $eventType,
        string $relatedType,
        int $relatedId,
    ): ChatSupportEvent {
        return DB::transaction(function () use ($conversation, $player, $eventType, $relatedType, $relatedId): ChatSupportEvent {
            User::whereKey($player->id)->lockForUpdate()->firstOrFail();
            $lockedConversation = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->authorization->assertPlayerOwns($lockedConversation, $player);

            $related = $this->ownedPendingRecord($player, $eventType, $relatedType, $relatedId);

            $recentExists = ChatSupportEvent::where('player_id', $player->id)
                ->where('event_type', $eventType)
                ->where('related_type', $relatedType)
                ->where('related_id', $related->getKey())
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();

            if ($recentExists) {
                throw new ReminderThrottledException;
            }

            return ChatSupportEvent::create([
                'conversation_id' => $lockedConversation->id,
                'player_id' => $player->id,
                'event_type' => $eventType,
                'related_type' => $relatedType,
                'related_id' => $related->getKey(),
                'status' => 'pending',
            ]);
        });
    }

    private function ownedPendingRecord(
        User $player,
        string $eventType,
        string $relatedType,
        int $relatedId,
    ): Model {
        $allowedRelatedTypes = match ($eventType) {
            'play_reminder' => ['brahma_play_request', 'deposit'],
            'deposit_reminder' => ['deposit'],
            'brahma_deposit_reminder' => ['brahma_deposit'],
            'cashout_reminder' => ['cashout'],
            default => [],
        };

        if (! in_array($relatedType, $allowedRelatedTypes, true) || $relatedId < 1) {
            throw new DomainException('The reminder target is invalid.');
        }

        $model = match ($relatedType) {
            'brahma_play_request' => BrahmaPlayRequest::class,
            'deposit' => Deposit::class,
            'brahma_deposit' => BrahmaDeposit::class,
            'cashout' => Cashout::class,
        };

        return $model::whereKey($relatedId)
            ->where('user_id', $player->id)
            ->where('status', 'pending')
            ->firstOrFail();
    }

    private function mainMenu(): array
    {
        return [
            ['label' => 'Pending game requests', 'value' => 'show_pending_plays'],
            ['label' => 'Pending deposits', 'value' => 'show_pending_deposits'],
            ['label' => 'Pending cashouts', 'value' => 'show_pending_cashouts'],
            ['label' => 'Brahma Balance', 'value' => 'show_brahma_balance'],
            ['label' => 'Contact Support', 'value' => 'request_human'],
        ];
    }

    private function assertPlayer(User $player): void
    {
        if (! $player->hasRole('player')) {
            throw new AuthorizationException('Chatbot player data is available only to players.');
        }
    }
}
