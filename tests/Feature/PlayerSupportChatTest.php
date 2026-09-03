<?php

namespace Tests\Feature;

use App\Livewire\Player\PlayerSupportChat;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\ChatbotRule;
use App\Models\ChatSupportEvent;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\DefaultChatbotIntentService;
use App\Services\Chat\PlayerSupportChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

class PlayerSupportChatTest extends TestCase
{
    use RefreshDatabase;

    private PlayerSupportChatService $support;

    private ChatMessageService $messages;

    private ConversationService $conversations;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }

        $this->support = app(PlayerSupportChatService::class);
        $this->messages = app(ChatMessageService::class);
        $this->conversations = app(ConversationService::class);
    }

    public function test_player_component_access_and_single_layout_mount_are_role_scoped(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');

        Livewire::actingAs($player)
            ->test(PlayerSupportChat::class)
            ->assertSee('Open Brahmabull support chat');

        $playerPage = $this->actingAs($player)->get(route('games'));
        $playerPage->assertOk()->assertSeeLivewire('player.player-support-chat');
        $this->assertSame(1, substr_count($playerPage->getContent(), '&quot;name&quot;:&quot;player.player-support-chat&quot;'));

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSeeLivewire('player.player-support-chat');
        $this->actingAs($agent)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSeeLivewire('player.player-support-chat');

        Livewire::actingAs($admin)->test(PlayerSupportChat::class)->assertForbidden();
        Livewire::actingAs($agent)->test(PlayerSupportChat::class)->assertForbidden();

        auth()->logout();
        Livewire::test(PlayerSupportChat::class)->assertForbidden();
    }

    public function test_opening_initializes_reuses_and_replaces_resolved_support_conversations_once(): void
    {
        $player = $this->userWithRole('player');

        $first = $this->support->initialize($player);
        $same = $this->support->initialize($player);

        $this->assertTrue($first->is($same));
        $this->assertSame('support', $first->conversation_type);
        $this->assertSame('bot', $first->status);
        $this->assertSame(1, $first->messages()->count());
        $this->assertSame('options', $first->messages()->firstOrFail()->message_type);
        $this->assertCount(6, $first->messages()->firstOrFail()->metadata['options']);

        $admin = $this->userWithRole('admin');
        $this->conversations->resolveConversation($first, $admin);
        $next = $this->support->initialize($player);

        $this->assertFalse($first->is($next));
        $this->assertSame(1, $next->messages()->count());

        Livewire::actingAs($player)
            ->test(PlayerSupportChat::class)
            ->call('openChat')
            ->assertSet('isOpen', true)
            ->assertSet('conversationId', $next->id)
            ->assertSee('Hi! How can we help you today?')
            ->assertSee('My Play Request')
            ->call('closeChat')
            ->assertSet('isOpen', false);
    }

    public function test_player_safe_messages_mask_all_support_sender_identity_and_assignment_data(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $conversation = $this->support->initialize($player);

        $this->messages->sendPlayerMessage($conversation, $player, 'Player message');
        $this->messages->sendStaffMessage($conversation, $admin, 'Admin response');
        $this->conversations->reassignConversation($conversation->fresh(), $admin, $agent);
        $this->messages->sendStaffMessage($conversation->fresh(), $agent, 'Agent response');

        $safe = $this->support->messagesForPlayer($conversation->fresh(), $player);
        $encoded = json_encode($safe);

        $this->assertSame('You', collect($safe)->firstWhere('body', 'Player message')['display_name']);
        $this->assertSame('Brahmabull Support Team', collect($safe)->firstWhere('body', 'Admin response')['display_name']);
        $this->assertSame('Brahmabull Support Team', collect($safe)->firstWhere('body', 'Agent response')['display_name']);
        $this->assertStringNotContainsString($admin->name, $encoded);
        $this->assertStringNotContainsString($agent->name, $encoded);
        $this->assertStringNotContainsString('sender_id', $encoded);
        $this->assertStringNotContainsString('assigned_to', $encoded);
        $this->assertStringNotContainsString('assigned_by', $encoded);
    }

    public function test_database_rules_precede_defaults_and_default_aliases_and_fallback_work(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $conversation = $this->support->initialize($player);

        ChatbotRule::create([
            'name' => 'Custom exact',
            'trigger_type' => 'exact',
            'trigger_value' => ['what is my balance'],
            'response_text' => 'Configured exact response',
            'action_type' => 'none',
            'priority' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        ChatbotRule::create([
            'name' => 'Custom keyword',
            'trigger_type' => 'keyword',
            'trigger_value' => ['special keyword'],
            'response_text' => 'Configured keyword response',
            'action_type' => 'none',
            'priority' => 10,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->support->sendPlayerText($conversation, $player, 'what is my balance');
        $this->support->sendPlayerText($conversation, $player, 'please use special keyword now');
        $this->assertDatabaseHas('chat_messages', ['body' => 'Configured exact response', 'sender_type' => 'bot']);
        $this->assertDatabaseHas('chat_messages', ['body' => 'Configured keyword response', 'sender_type' => 'bot']);

        $aliases = app(DefaultChatbotIntentService::class);
        $this->assertSame('play', $aliases->match("My request isn't verified yet"));
        $this->assertSame('deposit', $aliases->match('deposit not verified'));
        $this->assertSame('cashout', $aliases->match('withdrawal pending'));
        $this->assertSame('balance', $aliases->match('show my balance'));
        $this->assertSame('support', $aliases->match('talk to someone'));

        $this->support->sendPlayerText($conversation, $player, 'completely unmatched words');
        $this->assertStringContainsString(
            "I didn't quite understand",
            $conversation->messages()->latest('id')->firstOrFail()->body,
        );

        ChatbotRule::create([
            'name' => 'Configured fallback',
            'trigger_type' => 'fallback',
            'response_text' => 'Configured fallback response',
            'action_type' => 'none',
            'priority' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $this->support->sendPlayerText($conversation, $player, 'another unknown question');
        $this->assertDatabaseHas('chat_messages', ['body' => 'Configured fallback response', 'sender_type' => 'bot']);
    }

    public function test_required_play_phrase_lists_only_owned_pending_requests_with_real_game_labels(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $game = $this->game('Vegas X');
        $conversation = $this->support->initialize($player);
        $pending = $this->brahmaPlay($player, $game, 'pending', 100);
        $normal = $this->normalDeposit($player, $game, 'pending', 50);
        $this->brahmaPlay($player, $game, 'verified', 25);
        $otherPending = $this->brahmaPlay($other, $game, 'pending', 999);

        $this->support->sendPlayerText($conversation, $player, "My request isn't verified yet");
        $optionsMessage = $conversation->messages()->latest('id')->firstOrFail();
        $options = collect($optionsMessage->metadata['options']);

        $this->assertStringContainsString('Which game request', $optionsMessage->body);
        $this->assertTrue($options->contains('value', 'remind:play:brahma_play_request:'.$pending->id));
        $this->assertTrue($options->contains('value', 'remind:play:deposit:'.$normal->id));
        $this->assertTrue($options->contains(fn (array $option) => str_contains($option['label'], 'Vegas X')));
        $this->assertFalse($options->contains('value', 'remind:play:brahma_play_request:'.$otherPending->id));
        $this->assertStringNotContainsString('999', json_encode($options));
    }

    public function test_play_reminder_is_revalidated_throttled_notified_and_financially_read_only(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $player->forceFill(['brahma_balance' => 150])->save();
        $conversation = $this->support->initialize($player);
        $request = $this->brahmaPlay($player, $this->game('Orion Stars'), 'pending', 100);
        $otherRequest = $this->brahmaPlay($other, $this->game('Other Game'), 'pending', 75);

        $this->support->selectOption($conversation, $player, 'remind:play:brahma_play_request:'.$request->id);

        $this->assertDatabaseHas('chat_support_events', [
            'conversation_id' => $conversation->id,
            'player_id' => $player->id,
            'event_type' => 'play_reminder',
            'related_type' => 'brahma_play_request',
            'related_id' => $request->id,
        ]);
        $event = ChatSupportEvent::where('related_id', $request->id)->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'chat_support_reminder',
            'entity_id' => $event->id,
        ]);

        $this->support->selectOption($conversation, $player, 'remind:play:brahma_play_request:'.$request->id);
        $this->assertSame(1, ChatSupportEvent::where('related_id', $request->id)->count());
        $this->assertSame(1, Notification::where('entity_id', $event->id)->where('type', 'chat_support_reminder')->count());
        $this->assertStringContainsString('already reminded', $conversation->messages()->latest('id')->firstOrFail()->body);

        $this->assertException(AuthorizationException::class, fn () => $this->support
            ->selectOption($conversation, $player, 'remind:play:brahma_play_request:'.$otherRequest->id));
        $request->update(['status' => 'verified']);
        $this->assertException(AuthorizationException::class, fn () => $this->support
            ->selectOption($conversation, $player, 'remind:play:brahma_play_request:'.$request->id));

        $this->assertSame('verified', $request->fresh()->status);
        $this->assertSame('150.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaBalanceTransaction::where('user_id', $player->id)->count());
    }

    public function test_deposit_flow_combines_scoped_pending_sources_and_reminders_do_not_mutate_them(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $this->userWithRole('admin');
        $game = $this->game('Fire Kirin');
        $conversation = $this->support->initialize($player);
        $normal = $this->normalDeposit($player, $game, 'pending', 50);
        $brahma = $this->brahmaDeposit($player, 'pending', 100);
        $completed = $this->normalDeposit($player, $game, 'approved', 75);
        $otherDeposit = $this->brahmaDeposit($other, 'pending', 500);

        $this->support->selectOption($conversation, $player, 'intent:deposit');
        $options = collect($conversation->messages()->latest('id')->firstOrFail()->metadata['options']);

        $this->assertTrue($options->contains('value', 'remind:deposit:deposit:'.$normal->id));
        $this->assertTrue($options->contains('value', 'remind:brahma_deposit:brahma_deposit:'.$brahma->id));
        $this->assertFalse($options->contains('value', 'remind:deposit:deposit:'.$completed->id));
        $this->assertFalse($options->contains('value', 'remind:brahma_deposit:brahma_deposit:'.$otherDeposit->id));

        $this->support->selectOption($conversation, $player, 'remind:deposit:deposit:'.$normal->id);
        $this->support->selectOption($conversation, $player, 'remind:brahma_deposit:brahma_deposit:'.$brahma->id);

        $this->assertDatabaseHas('chat_support_events', ['event_type' => 'deposit_reminder', 'related_id' => $normal->id]);
        $this->assertDatabaseHas('chat_support_events', ['event_type' => 'brahma_deposit_reminder', 'related_id' => $brahma->id]);
        $this->assertSame('pending', $normal->fresh()->status);
        $this->assertSame('pending', $brahma->fresh()->status);
    }

    public function test_cashout_flow_is_scoped_pending_only_and_reminder_is_read_only(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $this->userWithRole('admin');
        $game = $this->game('Cashout Game');
        $conversation = $this->support->initialize($player);
        $cashout = $this->cashout($player, $game, 'pending', 200);
        $paid = $this->cashout($player, $game, 'paid', 50);
        $otherCashout = $this->cashout($other, $game, 'pending', 999);

        $this->support->selectOption($conversation, $player, 'intent:cashout');
        $options = collect($conversation->messages()->latest('id')->firstOrFail()->metadata['options']);

        $this->assertTrue($options->contains('value', 'remind:cashout:cashout:'.$cashout->id));
        $this->assertFalse($options->contains('value', 'remind:cashout:cashout:'.$paid->id));
        $this->assertFalse($options->contains('value', 'remind:cashout:cashout:'.$otherCashout->id));

        $this->support->selectOption($conversation, $player, 'remind:cashout:cashout:'.$cashout->id);
        $this->assertDatabaseHas('chat_support_events', ['event_type' => 'cashout_reminder', 'related_id' => $cashout->id]);
        $this->assertSame('pending', $cashout->fresh()->status);
    }

    public function test_balance_other_main_menu_and_navigation_actions_are_safe(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 125.50])->save();
        $conversation = $this->support->initialize($player);

        $this->support->selectOption($conversation, $player, 'intent:balance');
        $balanceMessage = $conversation->messages()->latest('id')->firstOrFail();
        $this->assertSame('Your current Brahma Balance is $125.50.', $balanceMessage->body);
        $this->assertSame(route('games'), $this->support->selectOption($conversation, $player, 'navigate:games'));

        $this->support->selectOption($conversation, $player, 'intent:other');
        $this->assertSame('Please describe what you need help with.', $conversation->messages()->latest('id')->firstOrFail()->body);
        $this->support->selectOption($conversation, $player, 'intent:menu');
        $this->assertCount(6, $conversation->messages()->latest('id')->firstOrFail()->metadata['options']);

        $this->assertSame('125.50', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaBalanceTransaction::where('user_id', $player->id)->count());
    }

    public function test_human_handoff_notifies_admin_once_and_suppresses_bot_in_waiting_and_active_states(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $conversation = $this->support->initialize($player);

        $this->support->selectOption($conversation, $player, 'intent:support');
        $waiting = $conversation->fresh();
        $acknowledgement = $waiting->messages()->latest('id')->firstOrFail();

        $this->assertSame('waiting', $waiting->status);
        $this->assertNotNull($waiting->human_requested_at);
        $this->assertDatabaseHas('chat_support_events', [
            'conversation_id' => $waiting->id,
            'event_type' => 'human_support_requested',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'chat_human_support_requested',
        ]);
        $this->assertStringContainsString('Our support team has been notified', $acknowledgement->body);
        $this->assertStringNotContainsString('agent', strtolower($acknowledgement->body));
        $this->assertStringNotContainsString('admin', strtolower($acknowledgement->body));

        $beforeWaiting = $waiting->messages()->count();
        $this->support->sendPlayerText($waiting, $player, 'More context while waiting');
        $this->assertSame($beforeWaiting + 1, $waiting->messages()->count());

        $active = $this->conversations->takeConversation($waiting->fresh(), $admin);
        $beforeActive = $active->messages()->count();
        $this->support->sendPlayerText($active, $player, 'More context while active');
        $this->assertSame($beforeActive + 1, $active->messages()->count());

        $staffMessage = $this->messages->sendStaffMessage($active->fresh(), $admin, 'Private staff identity');
        $safe = collect($this->support->messagesForPlayer($active->fresh(), $player))->firstWhere('id', $staffMessage->id);
        $this->assertSame('Brahmabull Support Team', $safe['display_name']);
        $this->assertArrayNotHasKey('sender_id', $safe);
    }

    public function test_unread_badge_counts_support_only_and_opening_marks_messages_read(): void
    {
        $player = $this->userWithRole('player');
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $conversation = $this->support->initialize($player);

        $this->assertSame(1, $this->support->unreadCount($player));
        $this->messages->sendPlayerMessage($conversation, $player, 'Own message');
        $this->assertSame(1, $this->support->unreadCount($player));
        $this->messages->sendBotMessage($conversation, 'Unread support response');
        $this->assertSame(2, $this->support->unreadCount($player));

        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);
        $this->messages->sendInternalMessage($direct, $agentOne, 'Internal message');
        $this->assertSame(2, $this->support->unreadCount($player));

        Livewire::actingAs($player)
            ->test(PlayerSupportChat::class)
            ->assertSet('unreadCount', 2)
            ->call('openChat')
            ->assertSet('unreadCount', 0);

        $this->assertSame(0, $this->support->unreadCount($player));
    }

    public function test_component_revalidates_conversation_and_never_exposes_internal_chat_payloads(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $own = $this->support->initialize($player);
        $otherConversation = $this->support->initialize($other);
        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);

        $this->assertException(AuthorizationException::class, fn () => $this->support
            ->sendPlayerText($otherConversation, $player, 'Forged conversation'));
        $this->assertException(AuthorizationException::class, fn () => $this->support
            ->messagesForPlayer($direct, $player));

        Livewire::actingAs($player)
            ->test(PlayerSupportChat::class)
            ->set('conversationId', $otherConversation->id)
            ->set('message', 'Server-derived sender')
            ->call('sendMessage');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $own->id,
            'sender_type' => 'player',
            'sender_id' => $player->id,
            'body' => 'Server-derived sender',
        ]);
        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $otherConversation->id,
            'sender_id' => $player->id,
        ]);
    }

    public function test_widget_markup_has_exclusive_visible_polling_responsive_layers_and_no_teleport(): void
    {
        $view = file_get_contents(resource_path('views/livewire/player/player-support-chat.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/public.blade.php'));
        $privateLayout = file_get_contents(resource_path('views/layouts/private.blade.php'));

        $this->assertStringContainsString('wire:poll.2s="refreshUnread"', $view);
        $this->assertStringContainsString('wire:poll.3s.visible="pollOpen"', $view);
        $this->assertStringContainsString('z-[9000]', $view);
        $this->assertStringContainsString('z-[9997]', $view);
        $this->assertStringNotContainsString('@teleport', $view);
        $this->assertStringContainsString('<livewire:player.player-support-chat />', $layout);
        $this->assertSame(1, substr_count($layout, '<livewire:player.player-support-chat />'));
        $this->assertStringNotContainsString('player.player-support-chat', $privateLayout);
        $this->assertStringNotContainsString('attachment', strtolower($view));
        $this->assertStringNotContainsString('reaction', strtolower($view));
        $this->assertStringNotContainsString('reply_to', strtolower($view));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.fake()->unique()->numberBetween(1000, 999999),
            'role' => $role,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function game(string $name): Game
    {
        return Game::create([
            'name' => $name.' '.fake()->unique()->numberBetween(1, 999999),
            'slug' => 'support-game-'.fake()->unique()->numberBetween(1, 999999),
            'image' => 'games/support.jpg',
            'game_url' => 'https://example.com/game',
            'is_active' => true,
        ]);
    }

    private function brahmaPlay(User $player, Game $game, string $status, float $points): BrahmaPlayRequest
    {
        return BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => $points,
            'balance_at_submission' => $player->brahma_balance ?? 0,
            'status' => $status,
        ]);
    }

    private function normalDeposit(User $player, Game $game, string $status, float $amount): Deposit
    {
        return Deposit::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'wallet_type' => 'cashapp',
            'amount' => $amount,
            'proof_image' => 'proof.jpg',
            'status' => $status,
        ]);
    }

    private function brahmaDeposit(User $player, string $status, float $amount): BrahmaDeposit
    {
        return BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => $amount,
            'proof_image' => 'proof.jpg',
            'status' => $status,
        ]);
    }

    private function cashout(User $player, Game $game, string $status, float $amount): Cashout
    {
        $account = GameAccount::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'support-user-'.fake()->unique()->numberBetween(1, 999999),
            'game_password' => 'secret',
        ]);

        return Cashout::create([
            'reference' => 'CASH-SUPPORT-'.fake()->unique()->numberBetween(1, 999999),
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_account_id' => $account->id,
            'amount' => $amount,
            'wallet_type' => 'cashapp',
            'status' => $status,
        ]);
    }

    private function assertException(string $expected, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected exception [{$expected}] was not thrown.");
        } catch (Throwable $exception) {
            $this->assertInstanceOf($expected, $exception);
        }
    }
}
