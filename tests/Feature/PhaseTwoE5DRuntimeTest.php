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

    public function test_floating_activity_detection_keeps_one_alert_for_each_new_message(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        $alerts = Livewire::actingAs($viewer)->test(FloatingAlertCenter::class)
            ->assertSet('alerts', []);

        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Detected once');
        $alerts->call('pollAlerts')
            ->assertCount('alerts', 1)
            ->assertSee('Detected once');

        $alerts->call('pollAlerts')
            ->assertCount('alerts', 1);
    }

    public function test_same_mounted_team_bell_poll_renders_zero_one_two_and_warm_rows(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        $bell = Livewire::actingAs($viewer)->test(MessengerBell::class)
            ->assertSet('unreadCount', 0);

        $this->assertHiddenBadge($bell->html(), 0);

        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'First arrival');
        $bell->call('pollUnread')
            ->assertSet('unreadCount', 1);
        $this->assertVisibleBadge($bell->html(), 1);
        $bell->assertSet('recent.0.id', $direct->id)
            ->assertSet('recent.0.unread_count', 1)
            ->assertSet('recent.0.preview', 'First arrival');

        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Second arrival');
        $bell->call('pollUnread')
            ->assertSet('unreadCount', 2);
        $this->assertVisibleBadge($bell->html(), 2);
        $bell->assertSet('recent.0.unread_count', 2)
            ->assertSet('recent.0.preview', 'Second arrival');
    }

    public function test_same_mounted_team_list_poll_renders_zero_one_two_then_read_zero(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $selectedSender = $this->user('agent');
        $otherSender = $this->user('agent');
        $selected = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $selectedSender);
        $other = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $otherSender);
        $inbox = Livewire::actingAs($viewer)->test(TeamMessenger::class, ['initialConversationId' => $selected->id]);

        $this->assertReadRow($inbox->html(), $other->id);

        app(ChatMessageService::class)->sendInternalMessage($other, $otherSender, 'First elsewhere');
        $inbox->call('pollList')
            ->assertSet('selectedConversationId', $selected->id);
        $this->assertUnreadRow($inbox->html(), $other->id, 1);

        app(ChatMessageService::class)->sendInternalMessage($other, $otherSender, 'Second elsewhere');
        $inbox->call('pollList')
            ->assertSet('selectedConversationId', $selected->id);
        $this->assertUnreadRow($inbox->html(), $other->id, 2);

        $inbox->call('selectTeamConversation', $other->id)
            ->assertSet('selectedConversationId', $other->id)
            ->assertDispatchedTo(MessengerBell::class, 'messenger-unread-refresh');
        $this->assertReadRow($inbox->html(), $other->id);
    }

    public function test_support_activity_stays_in_the_support_alert_domain(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $player = $this->user('player');
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        $alerts = Livewire::actingAs($viewer)->test(FloatingAlertCenter::class);

        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Support only');
        $alerts->call('pollAlerts')
            ->assertCount('alerts', 1)
            ->assertSet('alerts.0.kind', 'support');
    }

    public function test_floating_alerts_cover_participant_team_types_and_exclude_own_and_oversight(): void
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
            $messages->sendInternalMessage($conversation, $senderForMessage, $body);
            $alerts->call('pollAlerts');
            $this->assertSame($conversation->id, collect($alerts->get('alerts'))->last()['conversation_id']);
        }

        $encrypted = $direct->messages()->create([
            'sender_type' => 'agent',
            'sender_id' => $sender->id,
            'message_type' => 'text',
            'body' => null,
            'encrypted_payload' => '{"ciphertext":"opaque"}',
        ]);
        $direct->update(['last_message_at' => now()]);
        $alerts->call('pollAlerts');
        $this->assertSame($encrypted->id, (int) str($alerts->get('alerts')[3]['key'])->afterLast(':')->value());

        $messages->sendInternalMessage($direct, $viewer, 'Own message');
        $alerts->call('pollAlerts')->assertCount('alerts', 4);
        $this->assertSame(0, Notification::count());

        $oversight = $conversations->createGroupConversation($sender, 'Oversight only', [$viewer, $third]);
        $adminAlerts = Livewire::actingAs($admin)->test(FloatingAlertCenter::class);
        $messages->sendInternalMessage($oversight, $sender, 'Observer chatter');
        $adminAlerts->call('pollAlerts')
            ->assertSet('alerts', []);
    }

    public function test_unread_queries_use_read_cursors_before_a_corrupted_joined_timestamp(): void
    {
        $viewer = $this->user('admin');
        $sender = $this->user('agent');
        $legacySender = $this->user('agent');
        $conversations = app(ConversationService::class);
        $messages = app(ChatMessageService::class);

        $idCursorConversation = $conversations->getOrCreateDirectConversation($viewer, $sender);
        $messages->sendInternalMessage($idCursorConversation, $sender, 'ID cursor baseline');
        $conversations->markInternalConversationRead($idCursorConversation, $viewer);
        $idCursorConversation->participants()->where('user_id', $viewer->id)->update([
            'joined_at' => now()->addHours(6),
        ]);

        $legacyConversation = $conversations->getOrCreateDirectConversation($viewer, $legacySender);
        $messages->sendInternalMessage($legacyConversation, $legacySender, 'Timestamp baseline');
        $conversations->markInternalConversationRead($legacyConversation, $viewer);
        $legacyConversation->participants()->where('user_id', $viewer->id)->update([
            'joined_at' => now()->addHours(6),
            'last_read_message_id' => null,
        ]);

        $this->travel(1)->seconds();
        $messages->sendInternalMessage($idCursorConversation, $sender, 'ID cursor incoming');
        $messages->sendInternalMessage($legacyConversation, $legacySender, 'Timestamp incoming');

        $bell = Livewire::actingAs($viewer)->test(MessengerBell::class)
            ->assertSet('unreadCount', 2);
        $this->assertSame(1, collect($bell->get('recent'))->firstWhere('id', $idCursorConversation->id)['unread_count']);
        $this->assertSame(1, collect($bell->get('recent'))->firstWhere('id', $legacyConversation->id)['unread_count']);

        $inbox = Livewire::actingAs($viewer)->test(TeamMessenger::class);
        $inbox->call('selectSection', 'direct');
        $this->assertUnreadRow($inbox->html(), $idCursorConversation->id, 1);
        $this->assertUnreadRow($inbox->html(), $legacyConversation->id, 1);
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

    public function test_polling_an_already_open_conversation_does_not_auto_read_new_message(): void
    {
        $admin = $this->user('admin');
        $sicario = $this->user('agent');

        $conversation = app(ConversationService::class)
            ->getOrCreateDirectConversation(
                $admin,
                $sicario
            );

        /*
         * Admin explicitly opens the conversation.
         * Existing unread state is therefore read.
         */
        $inbox = Livewire::actingAs($admin)
            ->test(
                TeamMessenger::class,
                [
                    'initialConversationId' => $conversation->id,
                ]
            );

        $this->assertSame(
            0,
            app(ConversationService::class)
                ->internalUnreadCount(
                    $conversation,
                    $admin
                )
        );

        /*
         * Sicario now replies while Admin still has
         * the conversation open.
         */
        app(ChatMessageService::class)
            ->sendInternalMessage(
                $conversation,
                $sicario,
                'hello'
            );

        /*
         * Normal selected-chat polling must load the
         * new message WITHOUT marking it read.
         */
        $inbox->call('pollSelected');
        $inbox->call('pollList');

        $this->assertSame(
            1,
            app(ConversationService::class)
                ->internalUnreadCount(
                    $conversation,
                    $admin
                )
        );

        $this->assertUnreadRow(
            $inbox->html(),
            $conversation->id,
            1
        );

        /*
         * Header must also retain unread count.
         */
        Livewire::actingAs($admin)
            ->test(MessengerBell::class)
            ->call('pollUnread')
            ->assertSet('unreadCount', 1);

        /*
         * Explicit read action simulates focusing/opening
         * the conversation.
         */
        $inbox->call(
            'markSelectedConversationRead'
        );

        $this->assertSame(
            0,
            app(ConversationService::class)
                ->internalUnreadCount(
                    $conversation,
                    $admin
                )
        );

        $this->assertReadRow(
            $inbox->html(),
            $conversation->id
        );
    }

    public function test_message_in_another_chat_stays_unread_and_highlighted_until_opened(): void
    {
        $admin = $this->user('admin');
        $sicario = $this->user('agent');
        $thirdPerson = $this->user('agent');

        $sicarioChat = app(ConversationService::class)
            ->getOrCreateDirectConversation(
                $admin,
                $sicario
            );

        $thirdChat = app(ConversationService::class)
            ->getOrCreateDirectConversation(
                $admin,
                $thirdPerson
            );

        /*
         * Admin currently has third person's conversation open.
         */
        $inbox = Livewire::actingAs($admin)
            ->test(
                TeamMessenger::class,
                [
                    'initialConversationId' => $thirdChat->id,
                ]
            );

        /*
         * Sicario sends while Admin is looking elsewhere.
         */
        app(ChatMessageService::class)
            ->sendInternalMessage(
                $sicarioChat,
                $sicario,
                'new message from sicario'
            );

        $inbox->call('pollSelected');
        $inbox->call('pollList');

        /*
         * Third person's selected conversation stays selected.
         */
        $inbox->assertSet(
            'selectedConversationId',
            $thirdChat->id
        );

        /*
         * Sicario remains unread.
         */
        $this->assertSame(
            1,
            app(ConversationService::class)
                ->internalUnreadCount(
                    $sicarioChat,
                    $admin
                )
        );

        $this->assertUnreadRow(
            $inbox->html(),
            $sicarioChat->id,
            1
        );

        /*
         * Header must also show 1.
         */
        Livewire::actingAs($admin)
            ->test(MessengerBell::class)
            ->call('pollUnread')
            ->assertSet('unreadCount', 1);

        /*
         * Now Admin explicitly opens Sicario.
         */
        $inbox->call(
            'selectTeamConversation',
            $sicarioChat->id
        );

        $this->assertSame(
            0,
            app(ConversationService::class)
                ->internalUnreadCount(
                    $sicarioChat,
                    $admin
                )
        );
    }

    public function test_runtime_does_not_auto_read_from_retained_composer_focus_or_custom_bridge(): void
    {
        $teamView = file_get_contents(
            resource_path(
                'views/livewire/admin/team-messenger.blade.php'
            )
        );

        $privateLayout = file_get_contents(
            resource_path(
                'views/layouts/private.blade.php'
            )
        );

        /*
         * No global custom bridge.
         */
        $this->assertStringNotContainsString(
            '__brahmaTeamLiveBridgeInstalled',
            $privateLayout
        );

        $this->assertStringNotContainsString(
            'brahma-team-live-detected',
            $privateLayout
        );

        /*
         * Incoming message itself must never mark a chat read.
         */
        $this->assertStringNotContainsString(
            'brahma-team-message-arrived',
            $teamView
        );

        /*
         * Merely retaining browser focus is not enough.
         */
        $this->assertStringNotContainsString(
            'x-on:focus="$wire.markSelectedConversationRead()"',
            $teamView
        );

        /*
         * Actual user interaction does mark it read.
         */
        $this->assertStringContainsString(
            'x-on:pointerdown="$wire.markSelectedConversationRead()"',
            $teamView
        );

        $this->assertStringContainsString(
            'x-on:input.debounce.500ms="$wire.markSelectedConversationRead()"',
            $teamView
        );

    }
}
