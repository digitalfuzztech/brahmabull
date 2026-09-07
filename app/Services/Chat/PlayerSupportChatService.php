<?php

namespace App\Services\Chat;

use App\Exceptions\ReminderThrottledException;
use App\Models\ChatbotRule;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSupportEvent;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class PlayerSupportChatService
{
    public const MAIN_MENU_OPTIONS = [
        ['label' => 'My Play Request', 'value' => 'intent:play'],
        ['label' => 'My Deposit', 'value' => 'intent:deposit'],
        ['label' => 'My Cashout', 'value' => 'intent:cashout'],
        ['label' => 'Brahma Balance', 'value' => 'intent:balance'],
        ['label' => 'Talk to Support', 'value' => 'intent:support'],
        ['label' => 'Other', 'value' => 'intent:other'],
    ];

    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ChatMessageService $messages,
        private readonly ChatbotRuleService $rules,
        private readonly ChatbotActionService $actions,
        private readonly DefaultChatbotIntentService $defaultIntents,
        private readonly ChatAuthorizationService $authorization,
        private readonly ChatSupportNotificationService $notifications,
        private readonly ChatbotMenuService $menu,
    ) {}

    public function initialize(User $player): ChatConversation
    {
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);

        $this->messages->sendBotMessageIfConversationEmpty(
            $conversation,
            'Hi! How can we help you today?',
            'options',
            ['options' => $this->mainMenuOptions()],
        );

        return $conversation->fresh();
    }

    public function messagesForPlayer(ChatConversation $conversation, User $player, int $limit = 100): array
    {
        $this->authorization->assertPlayerOwns($conversation, $player);

        return ChatMessage::with('conversation:id,conversation_type')
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->limit(min(max($limit, 1), 100))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => $message->toPlayerSafeArray())
            ->all();
    }

    public function unreadCount(User $player): int
    {
        $this->assertPlayer($player);

        $conversationId = ChatConversation::where('conversation_type', 'support')
            ->where('player_id', $player->id)
            ->whereIn('status', ChatConversation::OPEN_STATUSES)
            ->latest('id')
            ->value('id');

        if (! $conversationId) {
            return 0;
        }

        return ChatMessage::where('conversation_id', $conversationId)
            ->where('sender_type', '!=', 'player')
            ->whereNull('read_by_player_at')
            ->count();
    }

    public function markRead(ChatConversation $conversation, User $player): int
    {
        return $this->messages->markMessagesReadByPlayer($conversation, $player);
    }

    public function sendPlayerText(ChatConversation $conversation, User $player, string $input): void
    {
        $input = trim($input);

        if ($input === '' || mb_strlen($input) > 2000) {
            throw new DomainException('Support messages must contain between 1 and 2000 characters.');
        }

        $conversation = $this->ownedOpenConversation($conversation, $player);
        $this->messages->sendPlayerMessage($conversation, $player, $input);

        if ($conversation->status !== 'bot') {
            return;
        }

        if ($rule = $this->rules->matchWithoutFallback($input)) {
            $this->respondToRule($conversation, $player, $rule);

            return;
        }

        if ($intent = $this->defaultIntents->match($input)) {
            $this->runIntent($conversation, $player, $intent);

            return;
        }

        if ($fallback = $this->rules->fallback()) {
            $this->respondToRule($conversation, $player, $fallback, true);

            return;
        }

        $this->sendFallback($conversation);
    }

    public function selectOption(ChatConversation $conversation, User $player, string $value): ?string
    {
        $conversation = $this->ownedOpenConversation($conversation, $player);
        $selection = $this->resolveOption($player, $value);
        $this->messages->sendPlayerMessage($conversation, $player, $selection['label']);

        if ($conversation->status !== 'bot') {
            return null;
        }

        if (isset($selection['navigate'])) {
            return $selection['navigate'];
        }

        if (isset($selection['intent'])) {
            $this->runIntent($conversation, $player, $selection['intent']);

            return null;
        }

        if (isset($selection['action'])) {
            if (filled($selection['response_text'] ?? null)) {
                $this->messages->sendBotMessage($conversation, $selection['response_text']);
            }
            if ($selection['action'] === 'none') {
                $this->showOtherPrompt($conversation);
            } elseif ($selection['action'] === 'show_pending_deposits') {
                // The established Main Menu deposit entry intentionally combines normal and Brahma deposits.
                $this->runIntent($conversation, $player, 'deposit');
            } else {
                $this->runApplicationAction($conversation, $player, $selection['action'], []);
            }

            return null;
        }

        $this->createReminderAndRespond($conversation, $player, $selection);

        return null;
    }

    private function respondToRule(
        ChatConversation $conversation,
        User $player,
        ChatbotRule $rule,
        bool $isFallback = false,
    ): void {
        if (filled($rule->response_text)) {
            $this->messages->sendBotMessage($conversation, $rule->response_text);
        }

        $action = $rule->action_type ?? 'none';
        $this->actions->assertSupportedAction($action);

        if ($action !== 'none') {
            $this->runApplicationAction($conversation, $player, $action, $rule->action_config ?? []);
        } elseif ($isFallback) {
            $this->sendFallbackOptions($conversation);
        } elseif (blank($rule->response_text)) {
            $this->sendFallback($conversation);
        }
    }

    private function runIntent(ChatConversation $conversation, User $player, string $intent): void
    {
        match ($intent) {
            'play' => $this->showPendingPlays($conversation, $player),
            'deposit' => $this->showPendingDeposits($conversation, $player),
            'cashout' => $this->showPendingCashouts($conversation, $player),
            'balance' => $this->showBalance($conversation, $player),
            'support' => $this->requestHumanSupport($conversation, $player),
            'other' => $this->showOtherPrompt($conversation),
            'menu' => $this->showMainMenu($conversation),
            default => $this->sendFallback($conversation),
        };
    }

    private function runApplicationAction(
        ChatConversation $conversation,
        User $player,
        string $action,
        array $config,
    ): void {
        match ($action) {
            'show_main_menu' => $this->showMainMenu($conversation),
            'show_pending_plays' => $this->showPendingPlays($conversation, $player),
            'show_pending_deposits' => $this->showPendingNormalDeposits($conversation, $player),
            'show_pending_brahma_deposits' => $this->showPendingBrahmaDeposits($conversation, $player),
            'show_pending_cashouts' => $this->showPendingCashouts($conversation, $player),
            'show_brahma_balance' => $this->showBalance($conversation, $player),
            'request_human' => $this->requestHumanSupport($conversation, $player),
            'remind_play_request', 'remind_deposit', 'remind_brahma_deposit', 'remind_cashout' => $this->runConfiguredReminder($conversation, $player, $action, $config),
            'none' => null,
        };
    }

    private function showPendingPlays(ChatConversation $conversation, User $player): void
    {
        $records = $this->actions->pendingPlayRequests($player);

        if ($records->isEmpty()) {
            $this->messages->sendBotMessage(
                $conversation,
                "You don't currently have any pending play requests.",
                'options',
                ['options' => $this->menuAndSupportOptions()],
            );

            return;
        }

        $options = $records->map(function (array $record): array {
            $detail = $record['source'] === 'brahma_play_request'
                ? number_format((float) $record['requested_points'], 2).' points'
                : '$'.number_format((float) $record['amount'], 2);

            return [
                'label' => ($record['game'] ?: 'Game').' — '.$detail,
                'value' => 'remind:play:'.$record['source'].':'.$record['request_id'],
            ];
        })->all();

        $this->messages->sendBotMessage(
            $conversation,
            'Okay, I can help with that. Which game request are you waiting for?',
            'options',
            ['options' => $options],
        );
    }

    private function showPendingDeposits(ChatConversation $conversation, User $player): void
    {
        $normal = $this->actions->pendingNormalDeposits($player)->map(fn (array $record) => [
            'label' => 'Game Deposit'.($record['game'] ? ' — '.$record['game'] : '').' — $'.number_format((float) $record['amount'], 2),
            'value' => 'remind:deposit:deposit:'.$record['id'],
        ]);
        $brahma = $this->actions->pendingBrahmaDeposits($player)->map(fn (array $record) => [
            'label' => 'Brahma Balance Deposit — $'.number_format((float) $record['amount'], 2),
            'value' => 'remind:brahma_deposit:brahma_deposit:'.$record['id'],
        ]);
        $options = $normal->concat($brahma)->values()->all();

        if ($options === []) {
            $this->messages->sendBotMessage(
                $conversation,
                "You don't currently have any pending deposits.",
                'options',
                ['options' => $this->menuAndSupportOptions()],
            );

            return;
        }

        $this->messages->sendBotMessage(
            $conversation,
            'Which deposit do you need help with?',
            'options',
            ['options' => $options],
        );
    }

    private function showPendingNormalDeposits(ChatConversation $conversation, User $player): void
    {
        $options = $this->actions->pendingNormalDeposits($player)->map(fn (array $record) => [
            'label' => 'Game Deposit'.($record['game'] ? ' — '.$record['game'] : '').' — $'.number_format((float) $record['amount'], 2),
            'value' => 'remind:deposit:deposit:'.$record['id'],
        ])->all();

        $this->sendDepositOptions($conversation, $options);
    }

    private function showPendingBrahmaDeposits(ChatConversation $conversation, User $player): void
    {
        $options = $this->actions->pendingBrahmaDeposits($player)->map(fn (array $record) => [
            'label' => 'Brahma Balance Deposit — $'.number_format((float) $record['amount'], 2),
            'value' => 'remind:brahma_deposit:brahma_deposit:'.$record['id'],
        ])->all();

        $this->sendDepositOptions($conversation, $options);
    }

    private function sendDepositOptions(ChatConversation $conversation, array $options): void
    {
        $this->messages->sendBotMessage(
            $conversation,
            $options === [] ? "You don't currently have any pending deposits." : 'Which deposit do you need help with?',
            'options',
            ['options' => $options === [] ? $this->menuAndSupportOptions() : $options],
        );
    }

    private function showPendingCashouts(ChatConversation $conversation, User $player): void
    {
        $records = $this->actions->pendingCashouts($player);

        if ($records->isEmpty()) {
            $this->messages->sendBotMessage(
                $conversation,
                "You don't currently have any pending cashouts.",
                'options',
                ['options' => $this->menuAndSupportOptions()],
            );

            return;
        }

        $options = $records->map(fn (array $record) => [
            'label' => 'Cashout — $'.number_format((float) $record['amount'], 2).' — Pending',
            'value' => 'remind:cashout:cashout:'.$record['id'],
        ])->all();

        $this->messages->sendBotMessage(
            $conversation,
            'Which cashout do you need help with?',
            'options',
            ['options' => $options],
        );
    }

    private function showBalance(ChatConversation $conversation, User $player): void
    {
        $balance = $this->actions->currentBrahmaBalance($player);

        $this->messages->sendBotMessage(
            $conversation,
            'Your current Brahma Balance is $'.$balance.'.',
            'options',
            ['options' => [
                ['label' => 'Play Games', 'value' => 'navigate:games'],
                ['label' => 'Main Menu', 'value' => 'intent:menu'],
                ['label' => 'Talk to Support', 'value' => 'intent:support'],
            ]],
        );
    }

    private function requestHumanSupport(ChatConversation $conversation, User $player): void
    {
        $result = $this->conversations->requestHumanSupportWithEvent($conversation, $player);

        if ($result['created'] && $result['event'] instanceof ChatSupportEvent) {
            $this->notifications->notifyHumanSupportRequested($result['event'], $player);
        }

        $this->messages->sendBotMessage(
            $result['conversation'],
            'Our support team has been notified. You can continue typing here while we review your request.',
        );
    }

    private function showOtherPrompt(ChatConversation $conversation): void
    {
        $this->messages->sendBotMessage($conversation, 'Please describe what you need help with.');
    }

    private function showMainMenu(ChatConversation $conversation): void
    {
        $this->messages->sendBotMessage(
            $conversation,
            'How can we help?',
            'options',
            ['options' => $this->mainMenuOptions()],
        );
    }

    private function sendFallback(ChatConversation $conversation): void
    {
        $this->messages->sendBotMessage(
            $conversation,
            "I didn't quite understand that. You can choose one of the options below or ask to speak with our support team.",
            'options',
            ['options' => $this->menuAndSupportOptions()],
        );
    }

    private function sendFallbackOptions(ChatConversation $conversation): void
    {
        $this->messages->sendBotMessage(
            $conversation,
            'Choose what you would like to do next.',
            'options',
            ['options' => $this->menuAndSupportOptions()],
        );
    }

    private function createReminderAndRespond(ChatConversation $conversation, User $player, array $selection): void
    {
        try {
            $event = $this->actions->createReminder(
                $conversation,
                $player,
                $selection['event_type'],
                $selection['related_type'],
                $selection['related_id'],
            );
        } catch (ReminderThrottledException) {
            $this->messages->sendBotMessage(
                $conversation,
                "We've already reminded our support team about this request. We're still reviewing it.",
            );

            return;
        }

        $this->notifications->notifyReminderCreated($event, $player, $selection['subject']);
        $this->messages->sendBotMessage(
            $conversation,
            "I've reminded our support team about your ".$selection['subject'].". We'll update you as soon as possible.",
        );
    }

    private function runConfiguredReminder(
        ChatConversation $conversation,
        User $player,
        string $action,
        array $config,
    ): void {
        $eventType = match ($action) {
            'remind_play_request' => 'play_reminder',
            'remind_deposit' => 'deposit_reminder',
            'remind_brahma_deposit' => 'brahma_deposit_reminder',
            'remind_cashout' => 'cashout_reminder',
        };
        $relatedType = (string) ($config['related_type'] ?? match ($action) {
            'remind_play_request' => 'brahma_play_request',
            'remind_deposit' => 'deposit',
            'remind_brahma_deposit' => 'brahma_deposit',
            'remind_cashout' => 'cashout',
        });
        $relatedId = (int) ($config['related_id'] ?? 0);

        try {
            $event = $this->actions->createReminder($conversation, $player, $eventType, $relatedType, $relatedId);
        } catch (ReminderThrottledException) {
            $this->messages->sendBotMessage(
                $conversation,
                "We've already reminded our support team about this request. We're still reviewing it.",
            );

            return;
        }

        $this->notifications->notifyReminderCreated($event, $player, 'pending request');
    }

    private function resolveOption(User $player, string $value): array
    {
        if ($managed = $this->menu->resolve($value)) {
            return $managed;
        }
        $core = [
            'intent:play' => ['label' => 'My Play Request', 'intent' => 'play'],
            'intent:deposit' => ['label' => 'My Deposit', 'intent' => 'deposit'],
            'intent:cashout' => ['label' => 'My Cashout', 'intent' => 'cashout'],
            'intent:balance' => ['label' => 'Brahma Balance', 'intent' => 'balance'],
            'intent:support' => ['label' => 'Talk to Support', 'intent' => 'support'],
            'intent:other' => ['label' => 'Other', 'intent' => 'other'],
            'intent:menu' => ['label' => 'Main Menu', 'intent' => 'menu'],
            'navigate:games' => ['label' => 'Play Games', 'navigate' => route('games')],
        ];

        if (isset($core[$value])) {
            return $core[$value];
        }

        if (! preg_match('/^remind:(play|deposit|brahma_deposit|cashout):([a-z_]+):(\d+)$/', $value, $matches)) {
            throw new DomainException('The selected support option is invalid.');
        }

        return $this->resolveReminderSelection($player, $matches[1], $matches[2], (int) $matches[3]);
    }

    private function mainMenuOptions(): array
    {
        $options = $this->menu->activeOptions();

        return $options === [] ? self::MAIN_MENU_OPTIONS : $options;
    }

    private function resolveReminderSelection(User $player, string $kind, string $relatedType, int $relatedId): array
    {
        $record = match ($kind) {
            'play' => $this->actions->pendingPlayRequests($player)
                ->first(fn (array $item) => $item['source'] === $relatedType && $item['request_id'] === $relatedId),
            'deposit' => $relatedType === 'deposit'
                ? $this->actions->pendingNormalDeposits($player)->firstWhere('id', $relatedId)
                : null,
            'brahma_deposit' => $relatedType === 'brahma_deposit'
                ? $this->actions->pendingBrahmaDeposits($player)->firstWhere('id', $relatedId)
                : null,
            'cashout' => $relatedType === 'cashout'
                ? $this->actions->pendingCashouts($player)->firstWhere('id', $relatedId)
                : null,
        };

        if (! $record) {
            throw new AuthorizationException('The selected request is not available.');
        }

        return match ($kind) {
            'play' => $this->playReminderSelection($record),
            'deposit' => [
                'label' => 'Game Deposit'.($record['game'] ? ' — '.$record['game'] : '').' — $'.number_format((float) $record['amount'], 2),
                'subject' => 'game deposit',
                'event_type' => 'deposit_reminder',
                'related_type' => 'deposit',
                'related_id' => $relatedId,
            ],
            'brahma_deposit' => [
                'label' => 'Brahma Balance Deposit — $'.number_format((float) $record['amount'], 2),
                'subject' => 'Brahma Balance deposit',
                'event_type' => 'brahma_deposit_reminder',
                'related_type' => 'brahma_deposit',
                'related_id' => $relatedId,
            ],
            'cashout' => [
                'label' => 'Cashout — $'.number_format((float) $record['amount'], 2).' — Pending',
                'subject' => 'cashout',
                'event_type' => 'cashout_reminder',
                'related_type' => 'cashout',
                'related_id' => $relatedId,
            ],
        };
    }

    private function playReminderSelection(array $record): array
    {
        $detail = $record['source'] === 'brahma_play_request'
            ? number_format((float) $record['requested_points'], 2).' points'
            : '$'.number_format((float) $record['amount'], 2);
        $game = $record['game'] ?: 'game';

        return [
            'label' => $game.' — '.$detail,
            'subject' => $game.' request',
            'event_type' => 'play_reminder',
            'related_type' => $record['source'],
            'related_id' => $record['request_id'],
        ];
    }

    private function menuAndSupportOptions(): array
    {
        return [
            ['label' => 'Main Menu', 'value' => 'intent:menu'],
            ['label' => 'Talk to Support', 'value' => 'intent:support'],
        ];
    }

    private function ownedOpenConversation(ChatConversation $conversation, User $player): ChatConversation
    {
        $fresh = ChatConversation::whereKey($conversation->id)
            ->where('conversation_type', 'support')
            ->where('player_id', $player->id)
            ->whereIn('status', ChatConversation::OPEN_STATUSES)
            ->first();

        if (! $fresh) {
            throw new AuthorizationException('You cannot access this support conversation.');
        }

        $this->authorization->assertPlayerOwns($fresh, $player);

        return $fresh;
    }

    private function assertPlayer(User $player): void
    {
        if (! $player->hasRole('player')) {
            throw new AuthorizationException('Player support chat is available only to players.');
        }
    }
}
