<?php

namespace Tests\Feature;

use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\SupportInbox;
use App\Livewire\Admin\TeamMessenger;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatPresenceService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessengerOverviewService;
use App\Services\Chat\TeamInboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamMessengerUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_presence_heartbeat_expires_and_direct_details_show_only_other_staff_presence(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $player = $this->user('player');
        $presence = app(ChatPresenceService::class);
        $conversation = app(ConversationService::class)->getOrCreateDirectConversation($admin, $agent);
        $group = app(ConversationService::class)->createGroupConversation($agent, 'Presence Group', [$this->user('agent'), $this->user('agent')]);

        $presence->heartbeat($agent);
        $this->assertTrue($presence->isOnline($agent));
        $this->assertTrue(app(TeamInboxService::class)->details($conversation, $admin)['other_online']);
        $this->assertNull(app(TeamInboxService::class)->details($group, $agent)['other_online']);
        $this->assertFalse($presence->isOnline($player));
        $this->expectException(AuthorizationException::class);
        $presence->heartbeat($player);
    }

    public function test_missing_or_expired_presence_is_offline(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');
        $agent = $this->user('agent');
        $presence = app(ChatPresenceService::class);
        $this->assertFalse($presence->isOnline($agent));
        $presence->heartbeat($agent);
        Carbon::setTestNow(now()->addSeconds(ChatPresenceService::TTL_SECONDS + 1));
        $this->assertFalse($presence->isOnline($agent));
    }

    public function test_messenger_badge_counts_messages_across_authorized_support_and_team_only(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $otherA = $this->user('agent');
        $otherB = $this->user('agent');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $messages = app(ChatMessageService::class);
        $overview = app(MessengerOverviewService::class);
        $direct = $conversations->getOrCreateDirectConversation($admin, $agent);
        $messages->sendInternalMessage($direct, $agent, 'One');
        $messages->sendInternalMessage($direct, $agent, 'Two');
        $support = $conversations->getOrCreatePlayerConversation($player);
        $messages->sendPlayerMessage($support, $player, 'Support unread');
        $private = $conversations->getOrCreateDirectConversation($otherA, $otherB);
        $messages->sendInternalMessage($private, $otherA, 'Private');
        $group = $conversations->createGroupConversation($agent, 'Observed', [$otherA, $otherB]);
        $messages->sendInternalMessage($group, $agent, 'Observer unread is not fabricated');

        $this->assertSame(3, $overview->unreadCount($admin));
        $this->assertFalse($overview->recent($admin)->contains('id', $private->id));
        $this->assertFalse($overview->recent($admin)->contains('id', $group->id));
        $conversations->markInternalConversationRead($direct, $admin);
        $this->assertSame(1, $overview->unreadCount($admin));
    }

    public function test_messenger_bell_is_staff_only_heartbeats_and_first_click_deep_links(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $player = $this->user('player');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($admin, $agent);

        Livewire::actingAs($admin)->test(MessengerBell::class)
            ->assertSee('Messenger')
            ->call('openConversation', 'team', $direct->id)
            ->assertRedirect(route('admin.inbox', ['domain' => 'team', 'conversation' => $direct->id]));
        $this->assertTrue(app(ChatPresenceService::class)->isOnline($admin));
        Livewire::actingAs($agent)->test(MessengerBell::class)->assertOk();
        Livewire::actingAs($player)->test(MessengerBell::class)->assertForbidden();
        Livewire::test(MessengerBell::class)->assertForbidden();
        $this->assertSame(1, substr_count(file_get_contents(resource_path('views/components/private-header.blade.php')), '<livewire:admin.messenger-bell'));
    }

    public function test_authorized_deep_links_open_and_mark_read_while_private_direct_is_denied(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $other = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($admin, $agent);
        app(ChatMessageService::class)->sendInternalMessage($direct, $agent, 'Open me');

        Livewire::actingAs($admin)->test(TeamMessenger::class, ['initialConversationId' => $direct->id])
            ->assertSet('selectedConversationId', $direct->id)
            ->assertSee('Open me');
        $this->assertSame(0, app(ConversationService::class)->internalUnreadCount($direct, $admin));

        $private = app(ConversationService::class)->getOrCreateDirectConversation($agent, $other);
        Livewire::actingAs($admin)->test(TeamMessenger::class, ['initialConversationId' => $private->id])->assertForbidden();
    }

    public function test_support_and_group_deep_links_select_exact_authorized_conversations(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('agent');
        $memberA = $this->user('agent');
        $memberB = $this->user('agent');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $support = $conversations->getOrCreatePlayerConversation($player);
        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Support deep link');

        Livewire::actingAs($admin)->test(SupportInbox::class, [
            'domain' => 'support',
            'deepLinkedConversationId' => $support->id,
        ])->assertSet('selectedConversationId', $support->id)->assertSee('Support deep link');
        $this->assertNotNull($support->messages()->firstOrFail()->fresh()->read_by_staff_at);

        $group = $conversations->createGroupConversation($owner, 'Linked Group', [$memberA, $memberB]);
        Livewire::actingAs($memberA)->test(TeamMessenger::class, ['initialConversationId' => $group->id])
            ->assertSet('selectedConversationId', $group->id)
            ->assertSee('Linked Group');
    }

    public function test_internal_sender_can_edit_text_and_caption_but_others_support_and_observers_cannot(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $agentC = $this->user('agent');
        $admin = $this->user('admin');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $messages = app(ChatMessageService::class);
        $direct = $conversations->getOrCreateDirectConversation($agentA, $agentB);
        $message = $messages->sendInternalMessage($direct, $agentA, 'Original');
        $edited = $messages->editInternalMessage($message, $agentA, 'Edited');
        $this->assertSame('Edited', $edited->body);
        $this->assertNotNull($edited->edited_at);
        $this->assertThrows(fn () => $messages->editInternalMessage($message, $agentB, 'No'), AuthorizationException::class);

        $attachment = app(ChatAttachmentService::class)->sendWithUpload($direct, $agentA, UploadedFile::fake()->image('caption.jpg'), 'Caption');
        $this->assertSame('New caption', $messages->editInternalMessage($attachment->message, $agentA, 'New caption')->body);
        $group = $conversations->createGroupConversation($agentA, 'Observed', [$agentB, $agentC]);
        $groupMessage = $messages->sendInternalMessage($group, $agentA, 'Group');
        $this->assertThrows(fn () => $messages->editInternalMessage($groupMessage, $admin, 'No'), AuthorizationException::class);
        $support = $conversations->getOrCreatePlayerConversation($player);
        $supportMessage = $messages->sendPlayerMessage($support, $player, 'Support');
        $this->assertThrows(fn () => $messages->editInternalMessage($supportMessage, $player, 'No'), AuthorizationException::class);
    }

    public function test_delete_tombstones_message_removes_media_reactions_and_preserves_replies(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        $attachments = app(ChatAttachmentService::class);
        $messages = app(ChatMessageService::class);
        $attachment = $attachments->sendWithUpload($direct, $agentA, UploadedFile::fake()->image('delete.jpg'), 'Delete me');
        $path = $attachment->file_path;
        $messages->addReaction($attachment->message, $agentB, "\u{1F44D}");
        $reply = $messages->replyToInternalMessage($direct, $attachment->message, $agentB, 'Reply remains');
        $this->assertThrows(fn () => $messages->deleteInternalMessage($attachment->message, $agentB), AuthorizationException::class);
        $deleted = $messages->deleteInternalMessage($attachment->message, $agentA);

        $this->assertNull($deleted->body);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame($agentA->id, $deleted->deleted_by);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('chat_message_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseMissing('chat_message_reactions', ['message_id' => $deleted->id]);
        $this->assertDatabaseHas('chat_messages', ['id' => $reply->id, 'reply_to_message_id' => $deleted->id]);
        $timeline = app(TeamInboxService::class)->messages($direct, $agentB);
        $this->assertSame('This message was deleted.', collect($timeline)->firstWhere('id', $reply->id)['reply']['body']);
        $this->actingAs($agentB)->get(route('team.attachments.view', $attachment))->assertNotFound();
        $this->assertThrows(fn () => $messages->addReaction($deleted, $agentB, "\u{1F44D}"));
        $this->assertThrows(fn () => $messages->editInternalMessage($deleted, $agentA, 'No'), AuthorizationException::class);
        $latest = $messages->sendInternalMessage($direct, $agentA, 'Latest deletion');
        $messages->deleteInternalMessage($latest, $agentA);
        $this->assertSame('Message deleted', app(MessengerOverviewService::class)->recent($agentB)->firstWhere('id', $direct->id)['preview']);

        $player = $this->user('player');
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        $supportMessage = $messages->sendPlayerMessage($support, $player, 'Protected support');
        $this->assertThrows(fn () => $messages->deleteInternalMessage($supportMessage, $player), AuthorizationException::class);
    }

    public function test_team_markup_has_keyboard_reply_edit_delete_presence_and_bounded_viewport(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $bell = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $this->assertStringContainsString('x-on:keydown.enter', $view);
        $this->assertStringContainsString('!$event.shiftKey', $view);
        $this->assertStringContainsString('!$event.isComposing', $view);
        $this->assertStringContainsString('wire:submit="sendTeamMessage"', $view);
        $this->assertStringContainsString('min-h-0 flex-1', $view);
        $this->assertStringContainsString('overflow-y-auto', $view);
        $this->assertStringContainsString('shrink-0 border-t', $view);
        $this->assertStringContainsString('setReply', $view);
        $this->assertStringContainsString('startEdit', $view);
        $this->assertStringContainsString('deleteMessage', $view);
        $this->assertStringContainsString('This message was deleted.', $view);
        $this->assertStringContainsString('bg-emerald-400', $view);
        $this->assertStringContainsString('wire:poll.20s.visible', $bell);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
