<?php

namespace Tests\Feature;

use App\Exceptions\ConversationAlreadyHandledException;
use App\Livewire\Admin\SupportInbox;
use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatConversation;
use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatSupportNotificationService;
use App\Services\Chat\ChatTeamNotificationService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessengerOverviewService;
use App\Services\Chat\SupportInboxService;
use App\Services\Chat\TeamInboxService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoD2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_player_bubbles_and_support_inbox_use_compact_bounded_layouts(): void
    {
        $playerView = file_get_contents(resource_path('views/livewire/player/player-support-chat.blade.php'));
        $inboxView = file_get_contents(resource_path('views/livewire/admin/support-inbox.blade.php'));

        $this->assertStringContainsString('leading-normal', $playerView);
        $this->assertStringContainsString("{{ trim(\$chatMessage['body']) }}", $playerView);
        $this->assertStringNotContainsString("\n                                    {{ \$chatMessage['body'] }}\n", $playerView);
        $this->assertStringContainsString("@if(filled(\$chatMessage['body']))", $playerView);
        $this->assertStringContainsString('h-[calc(100dvh-9rem)]', $inboxView);
        $this->assertStringContainsString('min-h-0 flex-1 space-y-4 overflow-y-auto', $inboxView);
        $this->assertStringContainsString('shrink-0 border-t', $inboxView);
        $this->assertStringContainsString('min-h-0 overflow-y-auto border-l', $inboxView);
        $this->assertStringNotContainsString('min-h-[680px]', $inboxView);
    }

    public function test_agents_share_open_support_visibility_and_first_human_reply_claims_atomically(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $messages = app(ChatMessageService::class);
        $inbox = app(SupportInboxService::class);
        $bot = $conversations->getOrCreatePlayerConversation($player);
        $messages->sendPlayerMessage($bot, $player, 'Still chatting with support');

        $this->assertTrue($inbox->conversations($agentA, 'open')->contains('id', $bot->id));
        $this->assertSame($bot->id, $inbox->selectConversation($bot->id, $agentA)->id);
        $taken = $conversations->takeConversation($bot->fresh(), $agentA);
        $this->assertSame('active', $taken->status);
        $this->assertSame($agentA->id, $taken->assigned_to);

        $secondPlayer = $this->user('player');
        $claimable = $conversations->getOrCreatePlayerConversation($secondPlayer);
        $sent = $messages->sendStaffMessage($claimable, $agentA, 'We can help with this.');
        $this->assertSame('agent', $sent->sender_type);
        $this->assertSame('active', $claimable->fresh()->status);
        $this->assertSame($agentA->id, $claimable->fresh()->assigned_to);
        $this->assertNotNull($claimable->fresh()->first_staff_response_at);

        $this->assertSame($claimable->id, $inbox->selectConversation($claimable->id, $agentB)->id);
        $this->assertThrows(
            fn () => $messages->sendStaffMessage($claimable->fresh(), $agentB, 'Competing reply'),
            ConversationAlreadyHandledException::class,
        );
        $this->assertThrows(
            fn () => $conversations->resolveConversation($claimable->fresh(), $agentB),
            AuthorizationException::class,
        );
        $this->assertDatabaseMissing('chat_messages', ['body' => 'Competing reply']);

        Livewire::actingAs($agentB)->test(SupportInbox::class)
            ->call('selectConversation', $claimable->id)
            ->assertSee('being handled by another support member');
    }

    public function test_noticeboard_is_singleton_synced_and_has_publisher_read_only_permissions(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $player = $this->user('player');
        $noticeboards = app(BrahmaNoticeboardService::class);
        $first = $noticeboards->ensureAndSyncParticipants();
        $second = $noticeboards->ensureAndSyncParticipants();

        $this->assertTrue(Schema::hasColumn('chat_conversations', 'channel_key'));
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ChatConversation::where('channel_key', BrahmaNoticeboardService::CHANNEL_KEY)->count());
        $this->assertSame('internal_channel', $first->conversation_type);
        $this->assertSame('#brahma-noticeboard', $first->name);
        $this->assertEqualsCanonicalizing(
            [$admin->id, $agentA->id, $agentB->id],
            $first->activeParticipants()->pluck('user_id')->all(),
        );
        $this->assertFalse($first->activeParticipants()->where('user_id', $player->id)->exists());

        $messages = app(ChatMessageService::class);
        $post = $messages->sendInternalMessage($first, $admin, 'Maintenance at 8 PM');
        $this->assertSame(1, app(ConversationService::class)->internalChannelUnreadCount($agentA));
        $this->assertTrue(app(MessengerOverviewService::class)->recent($agentA)->contains('id', $first->id));
        app(TeamInboxService::class)->selectConversation($first->id, $agentA);
        $this->assertSame(0, app(ConversationService::class)->internalChannelUnreadCount($agentA));
        $this->assertThrows(fn () => $messages->sendInternalMessage($first, $agentA, 'No'), AuthorizationException::class);
        $this->assertThrows(fn () => $messages->replyToInternalMessage($first, $post, $admin, 'No reply'), DomainException::class);
        $this->assertThrows(fn () => $messages->addReaction($post, $agentA, '👍'), AuthorizationException::class);
        $this->assertThrows(fn () => app(ConversationService::class)->leaveGroup($first, $agentA), AuthorizationException::class);
        $this->assertThrows(fn () => app(ChatAuthorizationService::class)->assertCanViewInternal($first, $player), AuthorizationException::class);

        $edited = $messages->editInternalMessage($post, $admin, 'Maintenance at 9 PM');
        $this->assertSame('Maintenance at 9 PM', $edited->body);
        $this->assertNotNull($messages->deleteInternalMessage($edited, $admin)->deleted_at);

        $newAgent = $this->user('agent');
        $noticeboards->ensureAndSyncParticipants();
        $this->assertTrue($first->activeParticipants()->where('user_id', $newAgent->id)->exists());
        $this->assertSame(1, ChatConversation::where('channel_key', BrahmaNoticeboardService::CHANNEL_KEY)->count());

        Livewire::actingAs($agentA)->test(TeamMessenger::class, ['initialConversationId' => $first->id])
            ->assertSee('#brahma-noticeboard')
            ->assertSee('read-only for Agents');
    }

    public function test_message_delivery_uses_messenger_while_operational_notifications_remain(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $messages = app(ChatMessageService::class);
        $direct = $conversations->getOrCreateDirectConversation($agentA, $agentB);
        $messages->sendInternalMessage($direct, $agentA, 'Direct message');
        $group = $conversations->createGroupConversation($agentA, 'Operations', [$agentB, $this->user('agent')]);
        $messages->sendInternalMessage($group, $agentA, 'Group message');
        $noticeboard = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        $messages->sendInternalMessage($noticeboard, $admin, 'Noticeboard message');
        $support = $conversations->getOrCreatePlayerConversation($player);
        $messages->sendStaffMessage($support, $admin, 'Support message');

        $this->assertSame(0, Notification::count());
        $this->assertGreaterThanOrEqual(3, app(MessengerOverviewService::class)->unreadCount($agentB));
        $this->assertStringNotContainsString('notifyDirectRecipient', file_get_contents(app_path('Livewire/Admin/TeamMessenger.php')));

        app(ChatTeamNotificationService::class)->notifyAddedToGroup($group, $agentB, $agentA);
        $human = $conversations->getOrCreatePlayerConversation($this->user('player'));
        $result = $conversations->requestHumanSupportWithEvent($human, $human->player);
        app(ChatSupportNotificationService::class)->notifyHumanSupportRequested($result['event'], $human->player);
        $this->assertDatabaseHas('notifications', ['type' => 'chat_team_group_added']);
        $this->assertDatabaseHas('notifications', ['type' => 'chat_human_support_requested']);
    }

    public function test_messenger_dropdown_is_bounded_with_persistent_inbox_footer(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));

        $this->assertStringContainsString('max-h-80 overflow-y-auto', $view);
        $this->assertStringContainsString('Go To Inbox', $view);
        $this->assertStringContainsString('wire:click="goToInbox"', $view);
        $this->assertTrue(strpos($view, 'Go To Inbox') > strpos($view, 'max-h-80 overflow-y-auto'));
        $this->assertStringContainsString('int $limit = 15', file_get_contents(app_path('Services/Chat/MessengerOverviewService.php')));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
