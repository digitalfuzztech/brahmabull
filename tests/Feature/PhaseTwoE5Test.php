<?php

namespace Tests\Feature;

use App\Livewire\Admin\FloatingAlertCenter;
use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\SupportMessengerBell;
use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatConversation;
use App\Models\ChatConversationObserverRead;
use App\Models\ChatConversationParticipant;
use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatObserverReadService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\HeaderActivityService;
use App\Services\Chat\MessengerOverviewService;
use App\Services\Chat\SupportMessengerOverviewService;
use App\Services\Chat\TeamInboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoE5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_team_and_support_bells_are_separate_live_message_counters(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $player = $this->user('player');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        app(ConversationService::class)->markInternalConversationRead($direct, $agentB);
        $this->travel(1)->seconds();
        app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'Team unread');
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Support unread');

        $this->assertSame(1, app(MessengerOverviewService::class)->unreadCount($agentB));
        $this->assertSame(1, app(SupportMessengerOverviewService::class)->unreadCount($agentB));

        Livewire::actingAs($agentB)->test(MessengerBell::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('Team unread')
            ->assertSee('bg-purple-500/10');
        Livewire::actingAs($agentB)->test(SupportMessengerBell::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('Support unread')
            ->assertSee('Go To Support Inbox')
            ->assertSee('bg-cyan-500/10');
        Livewire::actingAs($agentB)->test(SupportMessengerBell::class)
            ->call('openConversation', $support->id)
            ->assertRedirect(route('agent.inbox', ['domain' => 'support', 'conversation' => $support->id]));
        Livewire::actingAs($player)->test(SupportMessengerBell::class)->assertForbidden();
        Livewire::test(SupportMessengerBell::class)->assertForbidden();

        Livewire::actingAs($agentB)->test(TeamMessenger::class, ['initialConversationId' => $direct->id]);
        $this->assertSame(0, app(MessengerOverviewService::class)->unreadCount($agentB));
        $this->assertSame(1, app(SupportMessengerOverviewService::class)->unreadCount($agentB));
        $this->assertSame(0, Notification::count());
        $this->assertNotNull($admin);
    }

    public function test_generic_internal_channel_uses_participant_unread_without_noticeboard_name_checks(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $channel = ChatConversation::create([
            'conversation_type' => 'internal_channel',
            'channel_key' => 'future-channel',
            'name' => '#future-channel',
            'created_by' => $admin->id,
        ]);
        foreach ([[$admin, 'owner'], [$agent, 'member']] as [$staff, $role]) {
            ChatConversationParticipant::create([
                'conversation_id' => $channel->id,
                'user_id' => $staff->id,
                'participant_role' => $role,
                'joined_at' => now(),
            ]);
        }
        app(ChatMessageService::class)->sendInternalMessage($channel, $admin, 'Future channel post');

        $this->assertSame(1, app(MessengerOverviewService::class)->unreadCount($agent));
        $row = app(TeamInboxService::class)->conversations($agent, 'channels')->firstWhere('id', $channel->id);
        $this->assertSame(1, $row['unread_count']);
        app(TeamInboxService::class)->selectConversation($channel->id, $agent);
        $this->assertSame(0, app(MessengerOverviewService::class)->unreadCount($agent));
    }

    public function test_team_poll_refreshes_non_selected_unread_without_marking_it_read(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $senderA = $this->user('agent');
        $senderB = $this->user('agent');
        $selected = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $senderA);
        $other = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $senderB);
        $component = Livewire::actingAs($viewer)->test(TeamMessenger::class, ['initialConversationId' => $selected->id]);

        app(ChatMessageService::class)->sendInternalMessage($other, $senderB, 'Arrived elsewhere');
        $component->call('pollTeam')
            ->assertSet('selectedConversationId', $selected->id);

        $row = collect($component->get('conversations'))->firstWhere('id', $other->id);
        $this->assertSame(1, $row['unread_count']);
        $this->assertSame(1, app(MessengerOverviewService::class)->unreadCount($viewer));
        $this->assertNull($other->participants()->where('user_id', $viewer->id)->value('last_read_at'));
    }

    public function test_admin_oversight_has_independent_observer_cursor_without_participation(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('agent');
        $members = [$this->user('agent'), $this->user('agent')];
        $group = app(ConversationService::class)->createGroupConversation($owner, 'Night Shift', $members);
        $oversightMessage = app(ChatMessageService::class)->sendInternalMessage($group, $owner, 'Oversight unread');

        $this->assertFalse($group->activeParticipants()->where('user_id', $admin->id)->exists());
        $this->assertSame(1, app(ChatObserverReadService::class)->unreadCount($admin));
        $this->assertSame(0, app(MessengerOverviewService::class)->unreadCount($admin));
        $row = app(TeamInboxService::class)->conversations($admin, 'oversight')->firstWhere('id', $group->id);
        $this->assertSame(1, $row['unread_count']);
        $this->assertFalse(collect(app(HeaderActivityService::class)->after($admin, $oversightMessage->id - 1, 0)['alerts'])
            ->contains('key', 'team-message:'.$oversightMessage->id));

        app(TeamInboxService::class)->selectConversation($group->id, $admin);
        $this->assertSame(0, app(ChatObserverReadService::class)->unreadCount($admin));
        $this->assertDatabaseHas('chat_conversation_observer_reads', [
            'conversation_id' => $group->id,
            'user_id' => $admin->id,
        ]);
        $this->assertFalse($group->activeParticipants()->where('user_id', $admin->id)->exists());

        $group->participants()->where('user_id', $members[1]->id)->update(['left_at' => now()]);
        app(ChatMessageService::class)->sendInternalMessage($group->fresh(), $owner, 'Below threshold');
        $this->assertSame(0, app(ChatObserverReadService::class)->unreadCount($admin));
        $this->assertThrows(
            fn () => app(ChatObserverReadService::class)->markRead($group->fresh(), $admin),
            AuthorizationException::class,
        );
        $this->assertSame(1, ChatConversationObserverRead::count());
    }

    public function test_floating_alerts_seed_cursors_deduplicate_and_protect_e2ee_preview(): void
    {
        $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'Historical unread');

        $component = Livewire::actingAs($agentB)->test(FloatingAlertCenter::class)
            ->assertSet('alerts', []);
        app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'New incoming');
        $component->call('pollAlerts')->assertCount('alerts', 1);
        $component->call('pollAlerts')->assertCount('alerts', 1);
        app(ChatMessageService::class)->sendInternalMessage($direct, $agentB, 'Own outgoing');
        $component->call('pollAlerts')->assertCount('alerts', 1);
        $key = $component->get('alerts')[0]['key'];
        $component->call('dismiss', $key)->assertSet('alerts', []);

        $message = $direct->messages()->create([
            'sender_type' => 'agent',
            'sender_id' => $agentA->id,
            'message_type' => 'text',
            'body' => null,
            'encrypted_payload' => '{"ciphertext":"opaque"}',
        ]);
        $direct->update(['last_message_at' => now()]);
        $alerts = app(HeaderActivityService::class)->after($agentB, $message->id - 1, 0)['alerts'];
        $encrypted = collect($alerts)->firstWhere('key', 'team-message:'.$message->id);
        $this->assertSame('Sent you an encrypted message', $encrypted['preview']);
        $this->assertStringNotContainsString('opaque', json_encode($encrypted));
    }

    public function test_floating_alerts_include_support_and_operational_notifications_with_safe_destinations(): void
    {
        $this->user('admin');
        $agent = $this->user('agent');
        $player = $this->user('player');
        $component = Livewire::actingAs($agent)->test(FloatingAlertCenter::class);
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Help please');
        Notification::create([
            'user_id' => $agent->id,
            'type' => 'chat_conversation_assigned',
            'title' => 'Support Assigned',
            'message' => 'A support conversation was assigned to you.',
            'action_url' => route('agent.inbox', ['domain' => 'support']),
            'is_read' => false,
        ]);

        $component->call('pollAlerts')->assertCount('alerts', 2);
        $alerts = collect($component->get('alerts'));
        $this->assertSame(['notification', 'support'], $alerts->pluck('kind')->sort()->values()->all());
        $supportKey = $alerts->firstWhere('kind', 'support')['key'];
        $component->call('open', $supportKey)
            ->assertRedirect(route('agent.inbox', ['domain' => 'support', 'conversation' => $support->id]));
    }

    public function test_header_and_composer_markup_expose_three_bells_aligned_controls_and_bounded_alerts(): void
    {
        $header = file_get_contents(resource_path('views/components/private-header.blade.php'));
        $teamBell = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $supportBell = file_get_contents(resource_path('views/livewire/admin/support-messenger-bell.blade.php'));
        $alerts = file_get_contents(resource_path('views/livewire/admin/floating-alert-center.blade.php'));
        $team = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));

        $this->assertStringContainsString('admin.messenger-bell', $header);
        $this->assertStringContainsString('admin.support-messenger-bell', $header);
        $this->assertStringContainsString('admin.notification-bell', $header);
        $this->assertStringContainsString('wire:poll.2s="pollUnread"', $teamBell);
        $this->assertStringContainsString('wire:poll.2s="refreshSupportMessenger"', $supportBell);
        $this->assertStringContainsString('wire:poll.2s="pollAlerts"', $alerts);
        $this->assertStringContainsString('array_slice($this->alerts, 0, 5)', file_get_contents(app_path('Livewire/Admin/FloatingAlertCenter.php')));
        $this->assertGreaterThanOrEqual(4, substr_count($team, 'h-12'));
        $this->assertStringContainsString('w-12 shrink-0', $team);
        $this->assertStringContainsString('$event.shiftKey', $team);
        $this->assertStringContainsString('$event.isComposing', $team);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
