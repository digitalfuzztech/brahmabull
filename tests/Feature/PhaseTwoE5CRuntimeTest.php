<?php

namespace Tests\Feature;

use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\SupportMessengerBell;
use App\Livewire\Admin\TeamMessenger;
use App\Models\User;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoE5CRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_same_team_bell_dom_changes_from_hidden_zero_to_visible_one_and_two(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        $component = Livewire::actingAs($viewer)->test(MessengerBell::class)
            ->assertSet('unreadCount', 0);

        $badge = $this->tagWith($component->html(), 'data-team-unread-badge');
        $this->assertStringContainsString('data-unread-count="0"', $badge);
        $this->assertStringContainsString('data-unread="false"', $badge);
        $this->assertStringContainsString(' hidden', $badge);
        $this->assertStringNotContainsString('x-show', $badge);

        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'First incoming message');
        $component->call('pollUnread')->assertSet('unreadCount', 1);
        $badge = $this->tagWith($component->html(), 'data-team-unread-badge');
        $this->assertStringContainsString('data-unread-count="1"', $badge);
        $this->assertStringContainsString('data-unread="true"', $badge);
        $this->assertStringContainsString('inline-flex', $badge);
        $this->assertStringNotContainsString(' hidden', $badge);
        $this->assertStringNotContainsString('display: none', $badge);
        $this->assertStringNotContainsString('display:none', $badge);
        $this->assertStringContainsString('>1</span>', $badge);

        $this->travel(1)->seconds();
        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Second incoming message');
        $component->call('pollUnread')->assertSet('unreadCount', 2);
        $badge = $this->tagWith($component->html(), 'data-team-unread-badge');
        $this->assertStringContainsString('data-unread-count="2"', $badge);
        $this->assertStringContainsString('inline-flex', $badge);
        $this->assertStringNotContainsString(' hidden', $badge);
        $this->assertStringNotContainsString('display:none', $badge);
        $this->assertStringContainsString('>2</span>', $badge);
    }

    public function test_same_team_messenger_dom_updates_an_unselected_row_and_clears_only_when_opened(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $selectedSender = $this->user('agent');
        $otherSender = $this->user('agent');
        $selected = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $selectedSender);
        $other = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $otherSender);
        $component = Livewire::actingAs($viewer)->test(TeamMessenger::class, ['initialConversationId' => $selected->id]);

        $row = $this->conversationRow($component->html(), $other->id);
        $this->assertStringContainsString('data-unread-count="0"', $row);
        $this->assertStringContainsString('data-unread="false"', $row);
        $this->assertStringNotContainsString('ring-purple-400/20', $row);

        app(ChatMessageService::class)->sendInternalMessage($other, $otherSender, 'Unread in another chat');
        $component->call('pollList')->assertSet('selectedConversationId', $selected->id);
        $row = $this->conversationRow($component->html(), $other->id);
        $this->assertStringContainsString('data-unread-count="1"', $row);
        $this->assertStringContainsString('data-unread="true"', $row);
        $this->assertStringContainsString('bg-purple-500/10', $row);
        $this->assertStringContainsString('ring-purple-400/20', $row);
        $this->assertStringContainsString('font-black text-white', $row);
        $this->assertMatchesRegularExpression('/data-team-conversation-unread[^>]*data-unread-count="1"[^>]*inline-flex[^>]*>1<\/span>/', $component->html());

        $component->call('selectTeamConversation', $other->id)
            ->assertSet('selectedConversationId', $other->id);
        $row = $this->conversationRow($component->html(), $other->id);
        $this->assertStringContainsString('data-unread-count="0"', $row);
        $this->assertStringContainsString('data-unread="false"', $row);
    }

    public function test_team_and_support_recent_rows_are_warm_without_an_open_request(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Warm Team row');

        $teamBell = Livewire::actingAs($viewer)->test(MessengerBell::class);
        $this->assertTrue(collect($teamBell->get('recent'))->contains('id', $direct->id));
        $teamBell->assertSee('Warm Team row')->assertSeeHtml('data-team-dropdown-conversation-id="'.$direct->id.'"');
        $this->assertTrue(collect($teamBell->get('recent'))->contains('id', $direct->id));
        $teamBell->call('pollUnread')->assertSee('Warm Team row');

        $player = $this->user('player');
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Warm Support row');

        $supportBell = Livewire::actingAs($viewer)->test(SupportMessengerBell::class);
        $this->assertTrue(collect($supportBell->get('recent'))->contains('id', $support->id));
        $supportBell->assertSee('Warm Support row')->assertSeeHtml('data-support-dropdown-conversation-id="'.$support->id.'"');
        $this->assertTrue(collect($supportBell->get('recent'))->contains('id', $support->id));
        $supportBell->call('refreshSupportMessenger')->assertSee('Warm Support row');
    }

    public function test_dropdown_visibility_is_client_owned_but_unread_visibility_is_not(): void
    {
        $team = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $support = file_get_contents(resource_path('views/livewire/admin/support-messenger-bell.blade.php'));

        $this->assertStringContainsString('x-data="{ dropdownOpen: false }"', $team);
        $this->assertStringContainsString('brahma-header-dropdown-open', $team.$support);
        $this->assertStringNotContainsString("@entangle('unreadCount')", $team);
        $this->assertStringNotContainsString('x-show="unread > 0"', $team);
        $this->assertStringContainsString("\$unreadCount > 0 ? 'inline-flex items-center justify-center' : 'hidden'", $team);
        $this->assertStringNotContainsString('$wire.openDropdown()', $team.$support);
        $this->assertStringNotContainsString('$wire.closeDropdown()', $team.$support);
        $this->assertStringContainsString('wire:poll.2s="refreshSupportMessenger"', $support);

        $list = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $this->assertStringContainsString('data-team-conversation-list wire:poll.2s="pollList"', $list);
        $this->assertStringContainsString('wire:poll.3s.visible="pollSelected"', $list);
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
