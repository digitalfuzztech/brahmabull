<?php

namespace Tests\Feature;

use App\Livewire\Admin\FloatingAlertCenter;
use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoE5DRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_floating_activity_detection_emits_one_team_arrival_event_for_each_new_batch(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        $alerts = Livewire::actingAs($viewer)->test(FloatingAlertCenter::class)
            ->assertSet('alerts', []);

        $message = app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Detected once');
        $alerts->call('pollAlerts')
            ->assertCount('alerts', 1)
            ->assertSee('Detected once')
            ->assertDispatched(
                'team-message-arrived',
                conversationIds: [$direct->id],
                messageIds: [$message->id],
            );

        $alerts->call('pollAlerts')
            ->assertCount('alerts', 1)
            ->assertNotDispatched('team-message-arrived');
    }

    public function test_same_mounted_team_bell_receives_arrival_event_and_renders_zero_one_two(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        $bell = Livewire::actingAs($viewer)->test(MessengerBell::class)
            ->assertSet('unreadCount', 0);

        $this->assertHiddenBadge($bell->html(), 0);

        $first = app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'First arrival');
        $bell->dispatch('team-message-arrived', conversationIds: [$direct->id], messageIds: [$first->id])
            ->assertSet('unreadCount', 1);
        $this->assertVisibleBadge($bell->html(), 1);

        $second = app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Second arrival');
        $bell->dispatch('team-message-arrived', conversationIds: [$direct->id], messageIds: [$second->id])
            ->assertSet('unreadCount', 2);
        $this->assertVisibleBadge($bell->html(), 2);
    }

    public function test_same_mounted_team_list_receives_arrival_event_and_renders_zero_one_two_then_read_zero(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $selectedSender = $this->user('agent');
        $otherSender = $this->user('agent');
        $selected = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $selectedSender);
        $other = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $otherSender);
        $inbox = Livewire::actingAs($viewer)->test(TeamMessenger::class, ['initialConversationId' => $selected->id]);

        $this->assertReadRow($inbox->html(), $other->id);

        $first = app(ChatMessageService::class)->sendInternalMessage($other, $otherSender, 'First elsewhere');
        $inbox->dispatch('team-message-arrived', conversationIds: [$other->id], messageIds: [$first->id])
            ->assertSet('selectedConversationId', $selected->id);
        $this->assertUnreadRow($inbox->html(), $other->id, 1);

        $second = app(ChatMessageService::class)->sendInternalMessage($other, $otherSender, 'Second elsewhere');
        $inbox->dispatch('team-message-arrived', conversationIds: [$other->id], messageIds: [$second->id])
            ->assertSet('selectedConversationId', $selected->id);
        $this->assertUnreadRow($inbox->html(), $other->id, 2);

        $inbox->call('selectTeamConversation', $other->id)
            ->assertSet('selectedConversationId', $other->id)
            ->assertDispatchedTo(MessengerBell::class, 'messenger-unread-refresh');
        $this->assertReadRow($inbox->html(), $other->id);
    }

    public function test_support_activity_does_not_emit_a_team_arrival_event(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $player = $this->user('player');
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        $alerts = Livewire::actingAs($viewer)->test(FloatingAlertCenter::class);

        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Support only');
        $alerts->call('pollAlerts')
            ->assertCount('alerts', 1)
            ->assertSet('alerts.0.kind', 'support')
            ->assertNotDispatched('team-message-arrived');
    }

    public function test_activity_signal_covers_participant_team_types_and_excludes_own_and_oversight(): void
    {
        $admin = $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $third = $this->user('agent');
        $conversations = app(ConversationService::class);
        $messages = app(ChatMessageService::class);
        $direct = $conversations->getOrCreateDirectConversation($viewer, $sender);
        $group = $conversations->createGroupConversation($sender, 'Participant group', [$viewer, $third]);
        $channel = ChatConversation::create([
            'conversation_type' => 'internal_channel',
            'channel_key' => 'runtime-channel',
            'name' => '#runtime-channel',
            'created_by' => $admin->id,
        ]);
        foreach ([[$admin, 'owner'], [$viewer, 'member']] as [$participant, $role]) {
            ChatConversationParticipant::create([
                'conversation_id' => $channel->id,
                'user_id' => $participant->id,
                'participant_role' => $role,
                'joined_at' => now(),
            ]);
        }
        $noticeboard = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        $alerts = Livewire::actingAs($viewer)->test(FloatingAlertCenter::class);

        foreach ([
            [$group, $sender, 'Group arrival'],
            [$channel, $admin, 'Channel arrival'],
            [$noticeboard, $admin, 'Noticeboard arrival'],
        ] as [$conversation, $senderForMessage, $body]) {
            $message = $messages->sendInternalMessage($conversation, $senderForMessage, $body);
            $alerts->call('pollAlerts')->assertDispatched(
                'team-message-arrived',
                conversationIds: [$conversation->id],
                messageIds: [$message->id],
            );
        }

        $encrypted = $direct->messages()->create([
            'sender_type' => 'agent',
            'sender_id' => $sender->id,
            'message_type' => 'text',
            'body' => null,
            'encrypted_payload' => '{"ciphertext":"opaque"}',
        ]);
        $direct->update(['last_message_at' => now()]);
        $alerts->call('pollAlerts')->assertDispatched(
            'team-message-arrived',
            conversationIds: [$direct->id],
            messageIds: [$encrypted->id],
        );

        $messages->sendInternalMessage($direct, $viewer, 'Own message');
        $alerts->call('pollAlerts')->assertNotDispatched('team-message-arrived');
        $this->assertSame(0, Notification::count());

        $oversight = $conversations->createGroupConversation($sender, 'Oversight only', [$viewer, $third]);
        $adminAlerts = Livewire::actingAs($admin)->test(FloatingAlertCenter::class);
        $messages->sendInternalMessage($oversight, $sender, 'Observer chatter');
        $adminAlerts->call('pollAlerts')
            ->assertSet('alerts', [])
            ->assertNotDispatched('team-message-arrived');
    }

    private function assertHiddenBadge(string $html, int $count): void
    {
        $badge = $this->tagWith($html, 'data-team-unread-badge');
        $this->assertStringContainsString('data-unread-count="'.$count.'"', $badge);
        $this->assertStringContainsString(' hidden', $badge);
    }

    private function assertVisibleBadge(string $html, int $count): void
    {
        $badge = $this->tagWith($html, 'data-team-unread-badge');
        $this->assertStringContainsString('data-unread-count="'.$count.'"', $badge);
        $this->assertStringContainsString('inline-flex', $badge);
        $this->assertStringNotContainsString(' hidden', $badge);
        $this->assertStringContainsString('>'.$count.'</span>', $badge);
    }

    private function assertReadRow(string $html, int $conversationId): void
    {
        $row = $this->conversationRow($html, $conversationId);
        $this->assertStringContainsString('data-unread-count="0"', $row);
        $this->assertStringContainsString('data-unread="false"', $row);
        $this->assertStringNotContainsString('ring-purple-400/20', $row);
    }

    private function assertUnreadRow(string $html, int $conversationId, int $count): void
    {
        $row = $this->conversationRow($html, $conversationId);
        $this->assertStringContainsString('data-unread-count="'.$count.'"', $row);
        $this->assertStringContainsString('data-unread="true"', $row);
        $this->assertStringContainsString('bg-purple-500/10 ring-1 ring-inset ring-purple-400/20', $row);
        $this->assertStringContainsString('font-black text-white', $row);
        $this->assertMatchesRegularExpression('/data-team-conversation-unread[^>]*data-unread-count="'.$count.'"[^>]*inline-flex[^>]*>'.$count.'<\/span>/', $row);
    }

    private function tagWith(string $html, string $attribute): string
    {
        preg_match('/<span[^>]*'.$attribute.'[^>]*>.*?<\/span>/s', $html, $matches);
        $this->assertNotEmpty($matches, "Could not find a span containing {$attribute}.");

        return $matches[0];
    }

    private function conversationRow(string $html, int $conversationId): string
    {
        preg_match('/<button[^>]*data-team-conversation-id="'.$conversationId.'"[^>]*>.*?<\/button>/s', $html, $matches);
        $this->assertNotEmpty($matches, "Could not find Team conversation row {$conversationId}.");

        return $matches[0];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
