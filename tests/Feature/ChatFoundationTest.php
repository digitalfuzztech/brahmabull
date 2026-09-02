<?php

namespace Tests\Feature;

use App\Exceptions\ConversationAlreadyHandledException;
use App\Exceptions\InvalidChatbotActionException;
use App\Exceptions\ReminderThrottledException;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\ChatbotRule;
use App\Models\ChatConversation;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\User;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatbotActionService;
use App\Services\Chat\ChatbotRuleService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatFoundationTest extends TestCase
{
    use RefreshDatabase;

    private ConversationService $conversations;

    private ChatMessageService $messages;

    private ChatbotActionService $actions;

    private ChatbotRuleService $rules;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }

        $this->conversations = app(ConversationService::class);
        $this->messages = app(ChatMessageService::class);
        $this->actions = app(ChatbotActionService::class);
        $this->rules = app(ChatbotRuleService::class);
    }

    public function test_player_reuses_one_open_conversation_and_gets_a_new_unique_reference_after_resolution(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');

        $first = $this->conversations->getOrCreatePlayerConversation($player);
        $same = $this->conversations->getOrCreatePlayerConversation($player);

        $this->assertTrue($first->is($same));
        $this->assertSame('bot', $first->status);
        $this->assertMatchesRegularExpression('/^CHAT-\d{8}-[A-Z0-9]{10}$/', $first->reference);
        $this->assertSame(1, ChatConversation::whereIn('status', ChatConversation::OPEN_STATUSES)->count());

        $this->conversations->resolveConversation($first, $admin);
        $second = $this->conversations->getOrCreatePlayerConversation($player);

        $this->assertFalse($first->is($second));
        $this->assertNotSame($first->reference, $second->reference);
        $this->assertSame(2, ChatConversation::where('player_id', $player->id)->count());

        $this->expectException(AuthorizationException::class);
        $this->conversations->viewConversation($first->fresh(), $player);
    }

    public function test_player_bot_admin_and_agent_messages_record_internal_senders_and_safe_output_hides_staff_identity(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);

        $playerMessage = $this->messages->sendPlayerMessage($conversation, $player, 'Hello');
        $botMessage = $this->messages->sendBotMessage($conversation, 'How can we help?');
        $this->conversations->requestHumanSupport($conversation, $player);
        $adminMessage = $this->messages->sendStaffMessage($conversation->fresh(), $admin, 'We are reviewing this.');

        $agentConversation = $this->waitingConversation($this->userWithRole('player'));
        $this->conversations->takeConversation($agentConversation, $agent);
        $agentMessage = $this->messages->sendStaffMessage($agentConversation->fresh(), $agent, 'Support response.');

        $this->assertSame(['player', 'bot', 'admin'], $conversation->messages()->orderBy('id')->pluck('sender_type')->all());
        $this->assertSame($player->id, $playerMessage->sender_id);
        $this->assertNull($botMessage->sender_id);
        $this->assertSame($admin->id, $adminMessage->sender_id);
        $this->assertSame('agent', $agentMessage->sender_type);
        $this->assertSame($agent->id, $agentMessage->sender_id);

        $safe = $adminMessage->toPlayerSafeArray();
        $this->assertSame('Brahmabull Support Team', $safe['display_name']);
        $this->assertFalse($safe['is_player']);
        $this->assertArrayNotHasKey('sender_id', $safe);
        $this->assertArrayNotHasKey('sender_type', $safe);
        $this->assertArrayNotHasKey('assigned_to', $safe);
        $this->assertNotContains($admin->name, $safe);
        $this->assertSame('You', $playerMessage->toPlayerSafeArray()['display_name']);
    }

    public function test_conversation_access_is_scoped_for_players_admins_and_agents(): void
    {
        $owner = $this->userWithRole('player');
        $otherPlayer = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $conversation = $this->waitingConversation($owner);

        $this->assertTrue($this->conversations->viewConversation($conversation, $owner)->is($conversation));
        $this->assertTrue($this->conversations->viewConversation($conversation, $admin)->is($conversation));
        $this->assertTrue($this->conversations->viewConversation($conversation, $agentOne)->is($conversation));

        try {
            $this->conversations->viewConversation($conversation, $otherPlayer);
            $this->fail('Another player accessed the conversation.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->conversations->takeConversation($conversation, $agentOne);
        $this->assertTrue($this->conversations->viewConversation($conversation->fresh(), $agentOne)->is($conversation));

        $this->assertTrue($this->conversations->viewConversation($conversation->fresh(), $agentTwo)->is($conversation));
        $this->expectException(AuthorizationException::class);
        app(ChatAuthorizationService::class)->assertCanReply($conversation->fresh(), $agentTwo);
    }

    public function test_human_request_and_first_take_follow_lifecycle_and_second_take_is_rejected(): void
    {
        $player = $this->userWithRole('player');
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);

        $waiting = $this->conversations->requestHumanSupport($conversation, $player);
        $this->assertSame('waiting', $waiting->status);
        $this->assertNotNull($waiting->human_requested_at);
        $this->assertDatabaseHas('chat_support_events', [
            'conversation_id' => $waiting->id,
            'player_id' => $player->id,
            'event_type' => 'human_support_requested',
            'status' => 'pending',
        ]);

        $active = $this->conversations->takeConversation($waiting, $agentOne);
        $this->assertSame('active', $active->status);
        $this->assertSame($agentOne->id, $active->assigned_to);
        $this->assertSame($agentOne->id, $active->assigned_by);
        $this->assertNotNull($active->assigned_at);

        $this->expectException(ConversationAlreadyHandledException::class);
        $this->conversations->takeConversation($waiting, $agentTwo);
    }

    public function test_admin_can_assign_and_reassign_but_agent_cannot_reassign(): void
    {
        $admin = $this->userWithRole('admin');
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $conversation = $this->waitingConversation($this->userWithRole('player'));

        $assigned = $this->conversations->assignConversation($conversation, $admin, $agentOne);
        $this->assertSame($agentOne->id, $assigned->assigned_to);
        $this->assertSame($admin->id, $assigned->assigned_by);

        $reassigned = $this->conversations->reassignConversation($assigned, $admin, $agentTwo);
        $this->assertSame($agentTwo->id, $reassigned->assigned_to);
        $this->assertSame($admin->id, $reassigned->assigned_by);

        $this->expectException(AuthorizationException::class);
        $this->conversations->reassignConversation($reassigned, $agentOne, $agentOne);
    }

    public function test_assigned_agent_and_admin_can_resolve_with_audited_resolution(): void
    {
        $agent = $this->userWithRole('agent');
        $conversation = $this->waitingConversation($this->userWithRole('player'));
        $this->conversations->takeConversation($conversation, $agent);

        $resolved = $this->conversations->resolveConversation($conversation->fresh(), $agent);
        $this->assertSame('resolved', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame($agent->id, $resolved->resolved_by);

        $admin = $this->userWithRole('admin');
        $adminConversation = $this->conversations->getOrCreatePlayerConversation($this->userWithRole('player'));
        $this->assertSame('resolved', $this->conversations->resolveConversation($adminConversation, $admin)->status);
    }

    public function test_rule_matching_is_deterministic_prioritized_and_ignores_disabled_rules(): void
    {
        $admin = $this->userWithRole('admin');
        $this->rule($admin, 'Fallback', 'fallback', null, 999);
        $this->rule($admin, 'Low keyword', 'keyword', ['waiting'], 10);
        $this->rule($admin, 'High keyword', 'keyword', ['request'], 20);
        $exact = $this->rule($admin, 'Exact', 'exact', ['request waiting'], 0);
        $this->rule($admin, 'Disabled exact', 'exact', ['disabled'], 100, false);
        $option = $this->rule($admin, 'Option', 'option', ['brahma_play:123'], 1);

        $this->assertTrue($exact->is($this->rules->match('  REQUEST   waiting ')));
        $this->assertSame('High keyword', $this->rules->match('My request is still open')->name);
        $this->assertSame('Low keyword', $this->rules->match('I am waiting')->name);
        $this->assertTrue($option->is($this->rules->match('brahma_play:123')));
        $this->assertSame('Fallback', $this->rules->match('something unmatched')->name);
        $this->assertSame('Fallback', $this->rules->match('disabled')->name);
    }

    public function test_only_admin_can_manage_rules_and_action_names_are_allowlisted(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $created = $this->rules->createRule($admin, [
            'name' => 'Balance',
            'trigger_type' => 'exact',
            'trigger_value' => ['balance'],
            'action_type' => 'show_brahma_balance',
            'created_by' => $agent->id,
        ]);

        $this->assertSame($admin->id, $created->created_by);

        try {
            $this->rules->createRule($agent, [
                'name' => 'Forbidden',
                'trigger_type' => 'fallback',
            ]);
            $this->fail('An agent managed chatbot rules.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidChatbotActionException::class);
        $this->rules->updateRule($created, $admin, ['action_type' => 'php_function_name']);
    }

    public function test_pending_and_balance_lookups_are_player_scoped_and_return_safe_existing_data(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 125.50])->save();
        $other = $this->userWithRole('player');
        $game = $this->game();

        $play = $this->brahmaPlay($player, $game, 'pending');
        $this->brahmaPlay($player, $game, 'verified');
        $this->brahmaPlay($other, $game, 'pending');
        $normal = $this->normalDeposit($player, $game, 'pending');
        $this->normalDeposit($other, $game, 'pending');
        $brahmaDeposit = $this->brahmaDeposit($player, 'pending');
        $this->brahmaDeposit($other, 'pending');
        $cashout = $this->cashout($player, $game, 'pending');
        $this->cashout($other, $game, 'pending');

        $plays = $this->actions->pendingPlayRequests($player);
        $this->assertEqualsCanonicalizing([$play->id, $normal->id], $plays->pluck('request_id')->all());
        $this->assertSame([$normal->id], $this->actions->pendingNormalDeposits($player)->pluck('id')->all());
        $this->assertSame([$brahmaDeposit->id], $this->actions->pendingBrahmaDeposits($player)->pluck('id')->all());
        $this->assertSame([$cashout->id], $this->actions->pendingCashouts($player)->pluck('id')->all());
        $this->assertSame('125.50', $this->actions->currentBrahmaBalance($player));
    }

    public function test_real_request_reminder_is_created_once_per_cooldown_and_other_players_records_are_denied(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);
        $request = $this->brahmaPlay($player, $this->game(), 'pending');
        $otherRequest = $this->brahmaPlay($other, $this->game(), 'pending');

        $event = $this->actions->createReminder(
            $conversation,
            $player,
            'play_reminder',
            'brahma_play_request',
            $request->id,
        );

        $this->assertSame('pending', $event->status);
        $this->assertSame($request->id, $event->related_id);

        try {
            $this->actions->createReminder($conversation, $player, 'play_reminder', 'brahma_play_request', $request->id);
            $this->fail('A reminder bypassed the cooldown.');
        } catch (ReminderThrottledException $exception) {
            $this->assertStringContainsString('already reminded', $exception->getMessage());
        }

        $this->expectException(ModelNotFoundException::class);
        $this->actions->createReminder($conversation, $player, 'play_reminder', 'brahma_play_request', $otherRequest->id);
    }

    public function test_chat_actions_and_reminders_do_not_mutate_phase_one_financial_records(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 80])->save();
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);
        $game = $this->game();
        $play = $this->brahmaPlay($player, $game, 'pending');
        $normal = $this->normalDeposit($player, $game, 'pending');
        $brahmaDeposit = $this->brahmaDeposit($player, 'pending');
        $cashout = $this->cashout($player, $game, 'pending');

        $this->actions->pendingPlayRequests($player);
        $this->actions->pendingNormalDeposits($player);
        $this->actions->pendingBrahmaDeposits($player);
        $this->actions->pendingCashouts($player);
        $this->actions->currentBrahmaBalance($player);
        $this->actions->createReminder($conversation, $player, 'play_reminder', 'brahma_play_request', $play->id);
        $this->actions->createReminder($conversation, $player, 'deposit_reminder', 'deposit', $normal->id);
        $this->actions->createReminder($conversation, $player, 'brahma_deposit_reminder', 'brahma_deposit', $brahmaDeposit->id);
        $this->actions->createReminder($conversation, $player, 'cashout_reminder', 'cashout', $cashout->id);

        $this->assertSame('pending', $play->fresh()->status);
        $this->assertSame('pending', $normal->fresh()->status);
        $this->assertSame('pending', $brahmaDeposit->fresh()->status);
        $this->assertSame('pending', $cashout->fresh()->status);
        $this->assertSame('80.00', $player->fresh()->brahma_balance);
    }

    public function test_read_state_and_conversation_message_timestamps_are_updated(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);

        $playerMessage = $this->messages->sendPlayerMessage($conversation, $player, 'Player message');
        $botMessage = $this->messages->sendBotMessage($conversation->fresh(), 'Bot message');
        $this->conversations->requestHumanSupport($conversation->fresh(), $player);
        $staffMessage = $this->messages->sendStaffMessage($conversation->fresh(), $admin, 'Staff message');

        $timestamps = $conversation->fresh();
        $this->assertNotNull($timestamps->last_message_at);
        $this->assertNotNull($timestamps->last_player_message_at);
        $this->assertNotNull($timestamps->last_staff_message_at);
        $this->assertNotNull($timestamps->first_staff_response_at);
        $this->assertNull($botMessage->read_by_player_at);
        $this->assertNull($playerMessage->read_by_staff_at);

        $this->assertSame(2, $this->messages->markMessagesReadByPlayer($conversation->fresh(), $player));
        $this->assertSame(1, $this->messages->markMessagesReadByStaff($conversation->fresh(), $admin));
        $this->assertNotNull($botMessage->fresh()->read_by_player_at);
        $this->assertNotNull($staffMessage->fresh()->read_by_player_at);
        $this->assertNotNull($playerMessage->fresh()->read_by_staff_at);
    }

    private function waitingConversation(User $player): ChatConversation
    {
        $conversation = $this->conversations->getOrCreatePlayerConversation($player);

        return $this->conversations->requestHumanSupport($conversation, $player);
    }

    private function rule(
        User $creator,
        string $name,
        string $type,
        ?array $value,
        int $priority,
        bool $active = true,
    ): ChatbotRule {
        return ChatbotRule::create([
            'name' => $name,
            'trigger_type' => $type,
            'trigger_value' => $value,
            'priority' => $priority,
            'is_active' => $active,
            'created_by' => $creator->id,
        ]);
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

    private function game(): Game
    {
        return Game::create([
            'name' => 'Chat Foundation Game '.fake()->unique()->numberBetween(1, 999999),
            'slug' => 'chat-game-'.fake()->unique()->numberBetween(1, 999999),
            'image' => 'games/chat.jpg',
            'game_url' => 'https://example.com/game',
            'is_active' => true,
        ]);
    }

    private function brahmaPlay(User $player, Game $game, string $status): BrahmaPlayRequest
    {
        return BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => 10,
            'balance_at_submission' => $player->brahma_balance ?? 0,
            'status' => $status,
        ]);
    }

    private function normalDeposit(User $player, Game $game, string $status): Deposit
    {
        return Deposit::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'wallet_type' => 'cashapp',
            'amount' => 20,
            'proof_image' => 'proof.jpg',
            'status' => $status,
        ]);
    }

    private function brahmaDeposit(User $player, string $status): BrahmaDeposit
    {
        return BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => 30,
            'proof_image' => 'proof.jpg',
            'status' => $status,
        ]);
    }

    private function cashout(User $player, Game $game, string $status): Cashout
    {
        $account = GameAccount::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'chat-user-'.fake()->unique()->numberBetween(1, 999999),
            'game_password' => 'secret',
        ]);

        return Cashout::create([
            'reference' => 'CASH-CHAT-'.fake()->unique()->numberBetween(1, 999999),
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_account_id' => $account->id,
            'amount' => 40,
            'wallet_type' => 'cashapp',
            'status' => $status,
        ]);
    }
}
