<?php

namespace Tests\Feature;

use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\TeamMessenger;
use App\Livewire\Player\PlayerSupportChat;
use App\Models\User;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\PlayerSupportChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoE5BRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_closed_player_widget_uses_lightweight_two_second_poll_on_same_instance(): void
    {
        $player = $this->user('player');
        $conversation = app(PlayerSupportChatService::class)->initialize($player);
        app(PlayerSupportChatService::class)->markRead($conversation, $player);
        $component = Livewire::actingAs($player)->test(PlayerSupportChat::class)
            ->assertSet('isOpen', false)
            ->assertSet('unreadCount', 0)
            ->assertSet('messages', [])
            ->assertSet('conversationId', null);

        app(ChatMessageService::class)->sendBotMessage($conversation, 'Incoming support response');
        $component->call('refreshUnread')
            ->assertSet('unreadCount', 1)
            ->assertSet('messages', [])
            ->assertSet('conversationId', null)
            ->assertSeeHtml('data-player-support-unread-badge')
            ->assertSee('1');

        $component->call('openChat')->assertSet('unreadCount', 0);
        $this->assertStringContainsString(
            'wire:poll.2s="refreshUnread"',
            file_get_contents(resource_path('views/livewire/player/player-support-chat.blade.php')),
        );
    }

    public function test_team_list_poll_renders_unread_badge_and_highlight_without_remount(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $senderA = $this->user('agent');
        $senderB = $this->user('agent');
        $selected = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $senderA);
        $other = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $senderB);
        $component = Livewire::actingAs($viewer)->test(TeamMessenger::class, ['initialConversationId' => $selected->id]);

        app(ChatMessageService::class)->sendInternalMessage($other, $senderB, 'Unread elsewhere');
        $component->call('pollList')
            ->assertSet('selectedConversationId', $selected->id)
            ->assertSeeHtml('data-team-conversation-id="'.$other->id.'" data-unread-count="1"')
            ->assertSeeHtml('bg-purple-500/10')
            ->assertSeeHtml('data-team-conversation-unread');

        $row = collect($component->get('conversations'))->firstWhere('id', $other->id);
        $this->assertSame(1, $row['unread_count']);

        $component->call('selectTeamConversation', $other->id)
            ->assertSet('selectedConversationId', $other->id)
            ->assertSeeHtml('data-team-conversation-id="'.$other->id.'" data-unread-count="0"')
            ->assertDispatchedTo(MessengerBell::class, 'messenger-unread-refresh');
    }

    public function test_private_header_has_one_team_bell_and_client_side_dropdown_protocol(): void
    {
        $header = file_get_contents(resource_path('views/components/private-header.blade.php'));
        $team = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $support = file_get_contents(resource_path('views/livewire/admin/support-messenger-bell.blade.php'));
        $notifications = file_get_contents(resource_path('views/livewire/admin/notification-bell.blade.php'));

        $this->assertSame(1, substr_count($header, '<livewire:admin.messenger-bell'));
        foreach ([$team, $support, $notifications] as $view) {
            $this->assertStringContainsString('brahma-header-dropdown-open', $view);
            $this->assertStringContainsString('x-on:click.outside', $view);
            $this->assertStringContainsString('x-on:keydown.escape.window', $view);
        }
        $this->assertStringContainsString('$dispatch(\'brahma-header-dropdown-open\'', $team);
        $this->assertStringContainsString('$dispatch(\'brahma-header-dropdown-open\'', $support);
        $this->assertStringContainsString('$dispatch(\'brahma-header-dropdown-open\'', $notifications);
        $this->assertStringNotContainsString('wire:click="toggle"', $team.$support.$notifications);
    }

    public function test_plaintext_and_e2ee_composers_share_exact_unoffset_geometry(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));

        $this->assertStringContainsString('data-plaintext-composer-row class="flex flex-nowrap items-end gap-2"', $view);
        $this->assertStringContainsString('data-e2ee-composer-row class="flex flex-nowrap items-end gap-2"', $view);
        $this->assertGreaterThanOrEqual(2, substr_count($view, 'm-0 flex h-12 w-12 shrink-0'));
        $this->assertGreaterThanOrEqual(2, substr_count($view, 'm-0 block h-12 min-h-12 min-w-0 flex-1 resize-none box-border'));
        $this->assertGreaterThanOrEqual(2, substr_count($view, 'm-0 flex h-12 shrink-0'));
        $this->assertStringContainsString('$event.shiftKey', $view);
        $this->assertStringContainsString('$event.isComposing', $view);

        $plaintextRow = substr($view, strpos($view, 'data-plaintext-composer-row'), 2200);
        $this->assertStringNotContainsString('<div class="min-w-0 flex-1">', $plaintextRow);
        $this->assertStringNotContainsString('mb-', substr($plaintextRow, 0, 1200));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
