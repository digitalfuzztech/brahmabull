<?php

namespace Tests\Feature;

use App\Livewire\Admin\NotificationBell;
use App\Livewire\Admin\SupportInbox;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\ChatConversation;
use App\Models\ChatSupportEvent;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatSupportNotificationService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\SupportInboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportInboxTest extends TestCase
{
    use RefreshDatabase;

    private ConversationService $conversations;

    private ChatMessageService $messages;

    private SupportInboxService $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }

        $this->conversations = app(ConversationService::class);
        $this->messages = app(ChatMessageService::class);
        $this->inbox = app(SupportInboxService::class);
    }

    public function test_inbox_routes_are_role_protected_and_sidebars_link_to_each_inbox(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $player = $this->userWithRole('player');

        $this->actingAs($admin)->get(route('admin.inbox'))->assertOk()->assertSee('Support Inbox')->assertSee(route('admin.inbox'));
        $this->actingAs($agent)->get(route('agent.inbox'))->assertOk()->assertSee('Support Inbox')->assertSee(route('agent.inbox'));
        $this->actingAs($player)->get(route('admin.inbox'))->assertForbidden();
        $this->actingAs($player)->get(route('agent.inbox'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.inbox'))->assertRedirect(route('login'));

        Livewire::actingAs($player)->test(SupportInbox::class)->assertForbidden();
    }

    public function test_support_lists_filters_search_and_sort_never_include_internal_conversations(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $firstPlayer = $this->userWithRole('player', ['name' => 'Alpha Player', 'username' => 'alpha-user']);
        $secondPlayer = $this->userWithRole('player', ['name' => 'Beta Player', 'username' => 'beta-user']);
        $waiting = $this->waitingConversation($firstPlayer);
        $assigned = $this->waitingConversation($secondPlayer);
        $this->conversations->assignConversation($assigned, $admin, $agent);
        $resolved = $this->conversations->resolveConversation($assigned->fresh(), $agent);
        $direct = $this->conversations->getOrCreateDirectConversation($agent, $otherAgent);

        $waiting->update(['last_message_at' => now()->subMinute()]);
        $resolved->update(['last_message_at' => now()]);

        $all = $this->inbox->conversations($admin, 'all');
        $this->assertSame([$resolved->id, $waiting->id], $all->pluck('id')->all());
        $this->assertFalse($all->contains('id', $direct->id));
        $this->assertSame([$waiting->id], $this->inbox->conversations($admin, 'waiting')->pluck('id')->all());
        $this->assertSame([$waiting->id], $this->inbox->conversations($admin, 'unassigned')->pluck('id')->all());
        $this->assertSame([$resolved->id], $this->inbox->conversations($admin, 'resolved')->pluck('id')->all());
        $this->assertSame([$waiting->id], $this->inbox->conversations($admin, 'all', 'alpha-user')->pluck('id')->all());
        $this->assertSame([$waiting->id], $this->inbox->conversations($admin, 'all', $waiting->reference)->pluck('id')->all());

        $this->assertSame([$waiting->id], $this->inbox->conversations($otherAgent, 'waiting')->pluck('id')->all());
        $this->assertSame([$resolved->id], $this->inbox->conversations($agent, 'resolved')->pluck('id')->all());
        $this->assertFalse($this->inbox->conversations($otherAgent, 'resolved')->contains('id', $resolved->id));
    }

    public function test_selection_is_reauthorized_and_marks_only_selected_player_messages_read(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $first = $this->waitingConversation($this->userWithRole('player'));
        $second = $this->waitingConversation($this->userWithRole('player'));
        $firstMessage = $this->messages->sendPlayerMessage($first, $first->player, 'First unread');
        $secondMessage = $this->messages->sendPlayerMessage($second, $second->player, 'Second unread');
        $this->conversations->takeConversation($first->fresh(), $agent);

        $this->inbox->selectConversation($first->id, $agent);
        $this->assertNotNull($firstMessage->fresh()->read_by_staff_at);
        $this->assertNull($secondMessage->fresh()->read_by_staff_at);

        $this->inbox->selectConversation($first->id, $otherAgent);
        $this->assertNotNull($firstMessage->fresh()->read_by_staff_at);
    }

    public function test_admin_and_agent_take_waiting_chat_and_stale_take_is_handled(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $conversation = $this->waitingConversation($this->userWithRole('player'));

        Livewire::actingAs($agent)
            ->test(SupportInbox::class)
            ->call('selectConversation', $conversation->id)
            ->call('takeConversation')
            ->assertHasNoErrors();

        $taken = $conversation->fresh();
        $this->assertSame('active', $taken->status);
        $this->assertSame($agent->id, $taken->assigned_to);
        $this->assertNotNull($taken->assigned_at);

        Livewire::actingAs($admin)
            ->test(SupportInbox::class)
            ->call('selectConversation', $conversation->id)
            ->call('takeConversation')
            ->assertHasErrors('conversation');
    }

    public function test_admin_assigns_and_reassigns_active_agents_with_notifications_but_agent_cannot_assign(): void
    {
        $admin = $this->userWithRole('admin');
        $firstAgent = $this->userWithRole('agent');
        $secondAgent = $this->userWithRole('agent');
        $player = $this->userWithRole('player');
        $conversation = $this->waitingConversation($player);

        Livewire::actingAs($admin)
            ->test(SupportInbox::class)
            ->call('selectConversation', $conversation->id)
            ->set('selectedAgentId', $firstAgent->id)
            ->call('assignConversation')
            ->set('selectedAgentId', $secondAgent->id)
            ->call('reassignConversation')
            ->assertHasNoErrors();

        $this->assertSame($secondAgent->id, $conversation->fresh()->assigned_to);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $firstAgent->id,
            'type' => 'chat_conversation_assigned',
            'action_url' => route('agent.inbox'),
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $secondAgent->id,
            'type' => 'chat_conversation_assigned',
        ]);

        Livewire::actingAs($firstAgent)
            ->test(SupportInbox::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedAgentId', $player->id)
            ->call('assignConversation')
            ->assertForbidden();
    }

    public function test_staff_replies_store_true_identity_set_first_response_once_and_player_output_stays_masked(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $player = $this->userWithRole('player');
        $conversation = $this->waitingConversation($player);
        $this->conversations->takeConversation($conversation, $agent);

        Livewire::actingAs($agent)
            ->test(SupportInbox::class)
            ->call('selectConversation', $conversation->id)
            ->set('message', 'Agent response')
            ->call('sendReply')
            ->assertSet('message', '');

        $firstResponseAt = $conversation->fresh()->first_staff_response_at;
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $agent->id,
            'body' => 'Agent response',
        ]);

        $this->messages->sendStaffMessage($conversation->fresh(), $admin, 'Admin response');
        $this->assertTrue($firstResponseAt->equalTo($conversation->fresh()->first_staff_response_at));

        $safe = $conversation->messages()->with('conversation')->whereIn('body', ['Agent response', 'Admin response'])->get()
            ->map->toPlayerSafeArray();
        $this->assertSame(['Brahmabull Support Team'], $safe->pluck('display_name')->unique()->values()->all());
        $this->assertStringNotContainsString($agent->name, $safe->toJson());
        $this->assertStringNotContainsString($admin->name, $safe->toJson());
        $this->assertStringNotContainsString('sender_id', $safe->toJson());

        Livewire::actingAs($otherAgent)
            ->test(SupportInbox::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('message', 'Forged response')
            ->call('sendReply')
            ->assertHasErrors('conversation');
        $this->assertDatabaseMissing('chat_messages', ['body' => 'Forged response']);
    }

    public function test_resolution_is_audited_and_resolved_conversations_reject_replies(): void
    {
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $conversation = $this->waitingConversation($this->userWithRole('player'));
        $this->conversations->takeConversation($conversation, $agent);

        Livewire::actingAs($agent)
            ->test(SupportInbox::class)
            ->call('selectConversation', $conversation->id)
            ->call('resolveConversation')
            ->assertSee('read-only');

        $resolved = $conversation->fresh();
        $this->assertSame('resolved', $resolved->status);
        $this->assertSame($agent->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);

        $this->expectException(AuthorizationException::class);
        $this->conversations->resolveConversation($resolved, $otherAgent);
    }

    public function test_player_context_and_support_event_handling_are_scoped_and_financially_read_only(): void
    {
        $admin = $this->userWithRole('admin');
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $game = $this->game();
        $conversation = $this->waitingConversation($player);
        $otherConversation = $this->waitingConversation($other);
        $play = $this->brahmaPlay($player, $game);
        $deposit = $this->normalDeposit($player, $game);
        $brahmaDeposit = $this->brahmaDeposit($player);
        $cashout = $this->cashout($player, $game);
        $otherPlay = $this->brahmaPlay($other, $game);
        $event = ChatSupportEvent::create([
            'conversation_id' => $conversation->id,
            'player_id' => $player->id,
            'event_type' => 'play_reminder',
            'related_type' => 'brahma_play_request',
            'related_id' => $play->id,
            'status' => 'pending',
        ]);
        ChatSupportEvent::create([
            'conversation_id' => $otherConversation->id,
            'player_id' => $other->id,
            'event_type' => 'play_reminder',
            'related_type' => 'brahma_play_request',
            'related_id' => $otherPlay->id,
            'status' => 'pending',
        ]);

        $context = $this->inbox->playerContext($conversation, $admin);
        $events = $this->inbox->supportEvents($conversation, $admin);
        $this->assertSame($player->id, $context['player']['id']);
        $this->assertSame([$play->reference], collect($context['brahma_plays'])->pluck('reference')->all());
        $this->assertSame([$deposit->reference], collect($context['deposits'])->pluck('reference')->all());
        $this->assertSame([$brahmaDeposit->reference], collect($context['brahma_deposits'])->pluck('reference')->all());
        $this->assertSame([$cashout->reference], collect($context['cashouts'])->pluck('reference')->all());
        $this->assertTrue(collect($events)->contains('id', $event->id));
        $this->assertStringContainsString($game->name, collect($events)->firstWhere('id', $event->id)['summary']);

        $this->inbox->markEventHandled($event, $conversation, $admin);
        $this->assertDatabaseHas('chat_support_events', ['id' => $event->id, 'status' => 'handled', 'handled_by' => $admin->id]);
        $this->assertSame('pending', $play->fresh()->status);
        $this->assertSame('pending', $deposit->fresh()->status);
        $this->assertSame('pending', $brahmaDeposit->fresh()->status);
        $this->assertSame('pending', $cashout->fresh()->status);
    }

    public function test_new_support_and_assignment_notifications_deep_link_with_existing_one_click_navigation(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $player = $this->userWithRole('player');
        $conversation = $this->waitingConversation($player);
        $event = $conversation->supportEvents()->firstOrFail();
        $notifications = app(ChatSupportNotificationService::class);

        $notifications->notifyHumanSupportRequested($event, $player);
        $notifications->notifyConversationAssigned($conversation, $agent, $admin);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'chat_human_support_requested',
            'action_url' => route('admin.inbox'),
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $agent->id,
            'type' => 'chat_conversation_assigned',
            'action_url' => route('agent.inbox'),
        ]);

        $adminNotification = Notification::where('user_id', $admin->id)->firstOrFail();
        Livewire::actingAs($admin)
            ->test(NotificationBell::class)
            ->call('openNotification', $adminNotification->id)
            ->assertRedirect(route('admin.inbox'));
        $this->assertTrue((bool) $adminNotification->fresh()->is_read);
    }

    public function test_internal_direct_privacy_is_unchanged_by_support_inbox(): void
    {
        $admin = $this->userWithRole('admin');
        $firstAgent = $this->userWithRole('agent');
        $secondAgent = $this->userWithRole('agent');
        $direct = $this->conversations->getOrCreateDirectConversation($firstAgent, $secondAgent);

        $this->assertFalse($this->inbox->conversations($admin, 'all')->contains('id', $direct->id));

        $this->expectException(AuthorizationException::class);
        app(ChatAuthorizationService::class)->assertCanView($direct, $admin);
    }

    private function waitingConversation(User $player): ChatConversation
    {
        return $this->conversations->requestHumanSupport(
            $this->conversations->getOrCreatePlayerConversation($player),
            $player,
        );
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'username' => $role.fake()->unique()->numberBetween(1000, 999999),
            'role' => $role,
            'is_active' => true,
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function game(): Game
    {
        return Game::create([
            'name' => 'Inbox Game '.fake()->unique()->numberBetween(1, 999999),
            'slug' => 'inbox-game-'.fake()->unique()->numberBetween(1, 999999),
            'image' => 'games/inbox.jpg',
            'game_url' => 'https://example.com/game',
            'is_active' => true,
        ]);
    }

    private function brahmaPlay(User $player, Game $game): BrahmaPlayRequest
    {
        return BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => 100,
            'balance_at_submission' => 100,
            'status' => 'pending',
        ]);
    }

    private function normalDeposit(User $player, Game $game): Deposit
    {
        return Deposit::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'wallet_type' => 'cashapp',
            'amount' => 50,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);
    }

    private function brahmaDeposit(User $player): BrahmaDeposit
    {
        return BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => 60,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);
    }

    private function cashout(User $player, Game $game): Cashout
    {
        $account = GameAccount::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'inbox-user-'.fake()->unique()->numberBetween(1, 999999),
            'game_password' => 'secret',
        ]);

        return Cashout::create([
            'reference' => 'CASH-INBOX-'.fake()->unique()->numberBetween(1, 999999),
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_account_id' => $account->id,
            'amount' => 40,
            'wallet_type' => 'cashapp',
            'status' => 'pending',
        ]);
    }
}
