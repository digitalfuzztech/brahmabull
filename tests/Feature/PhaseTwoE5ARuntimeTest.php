<?php

namespace Tests\Feature;

use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\NotificationBell;
use App\Livewire\Admin\SupportMessengerBell;
use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\HeaderActivityService;
use App\Services\Chat\MessengerOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoE5ARuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_same_mounted_team_bell_poll_updates_for_successive_incoming_messages(): void
    {
        $this->user('admin');
        $viewer = $this->user('agent');
        $sender = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($viewer, $sender);
        $component = Livewire::actingAs($viewer)->test(MessengerBell::class)
            ->assertSet('unreadCount', 0);

        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'First');
        $component->call('pollUnread')
            ->assertSet('unreadCount', 1)
            ->assertSeeHtml('data-team-unread-badge')
            ->assertSeeHtml('>1</span>');

        $this->travel(1)->seconds();
        app(ChatMessageService::class)->sendInternalMessage($direct, $sender, 'Second');
        $component->call('pollUnread')
            ->assertSet('unreadCount', 2)
            ->assertSeeHtml('>2</span>');
    }

    public function test_global_team_surfaces_include_participants_and_exclude_oversight(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('agent');
        $members = [$this->user('agent'), $this->user('agent')];
        $group = app(ConversationService::class)->createGroupConversation($owner, 'Oversight only', $members);
        $message = app(ChatMessageService::class)->sendInternalMessage($group, $owner, 'Private group activity');

        $this->assertSame(0, app(MessengerOverviewService::class)->unreadCount($admin));
        $this->assertFalse(app(MessengerOverviewService::class)->recent($admin)->contains('id', $group->id));
        $this->assertFalse(collect(app(HeaderActivityService::class)->after($admin, $message->id - 1, 0)['alerts'])
            ->contains('key', 'team-message:'.$message->id));

        Livewire::actingAs($admin)->test(TeamMessenger::class)
            ->call('selectSection', 'oversight')
            ->assertSee('Oversight only')
            ->assertSee('bg-purple-500/10', false)
            ->assertSee('>1<', false);
        $this->assertFalse($group->activeParticipants()->where('user_id', $admin->id)->exists());
    }

    public function test_arbitrary_channel_updates_team_list_on_the_same_component(): void
    {
        $admin = $this->user('admin');
        $viewer = $this->user('agent');
        $channel = ChatConversation::create([
            'conversation_type' => 'internal_channel',
            'channel_key' => 'operations',
            'name' => '#operations',
            'created_by' => $admin->id,
        ]);
        foreach ([[$admin, 'owner'], [$viewer, 'member']] as [$user, $role]) {
            ChatConversationParticipant::create([
                'conversation_id' => $channel->id,
                'user_id' => $user->id,
                'participant_role' => $role,
                'joined_at' => now(),
            ]);
        }

        $component = Livewire::actingAs($viewer)->test(TeamMessenger::class, ['initialSection' => 'channels'])
            ->assertSee('#operations');
        app(ChatMessageService::class)->sendInternalMessage($channel, $admin, 'Incoming channel post');
        $component->call('pollTeam')
            ->assertSee('bg-purple-500/10', false)
            ->assertSee('>1<', false);
    }

    public function test_header_dropdowns_use_only_the_shared_client_visibility_protocol(): void
    {
        $team = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $support = file_get_contents(resource_path('views/livewire/admin/support-messenger-bell.blade.php'));
        $notifications = file_get_contents(resource_path('views/livewire/admin/notification-bell.blade.php'));

        foreach ([$team, $support, $notifications] as $view) {
            $this->assertStringContainsString('brahma-header-dropdown-open', $view);
            $this->assertStringContainsString('dropdownOpen = false', $view);
            $this->assertStringNotContainsString('$wire.openDropdown()', $view);
            $this->assertStringNotContainsString('$wire.closeDropdown()', $view);
        }

        $this->assertFalse(property_exists(MessengerBell::class, 'open'));
        $this->assertFalse(property_exists(SupportMessengerBell::class, 'open'));
        $this->assertFalse(property_exists(NotificationBell::class, 'open'));
    }

    public function test_team_header_markup_uses_stable_closed_state_poll_and_no_cross_component_set(): void
    {
        $bell = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $header = file_get_contents(resource_path('views/components/private-header.blade.php'));

        $this->assertStringContainsString('wire:poll.2s="pollUnread"', $bell);
        $this->assertStringNotContainsString('wire:poll.2s.visible="pollUnread"', $bell);
        $this->assertStringContainsString("unread_count'] > 99 ? '99+'", $bell);
        $this->assertStringContainsString('admin.messenger-bell', $header);
        $this->assertStringContainsString('admin.support-messenger-bell', $header);
        $this->assertStringContainsString('admin.notification-bell', $header);
        $this->assertStringNotContainsString('$set(\'open\'', $header.$bell);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
