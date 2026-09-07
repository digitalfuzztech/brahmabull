<?php

namespace Tests\Feature;

use App\Livewire\Admin\ChatSettings;
use App\Models\ChatbotMenuItem;
use App\Models\ChatbotRule;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatbotRuleService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\InternalChannelService;
use App\Services\Chat\MessengerOverviewService;
use App\Services\Chat\TeamInboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_admin_manages_rules_menu_and_preview_without_side_effects_while_agent_is_denied(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $this->actingAs($admin)->get(route('admin.chat-settings'))->assertOk()->assertSee('Chatbot')->assertSee('Channels');
        $this->actingAs($agent)->get('/admin/chat-settings')->assertForbidden();

        Livewire::actingAs($admin)->test(ChatSettings::class)
            ->set('ruleName', 'Pending Cashout')->set('triggerType', 'exact')
            ->set('triggerTerms', "where is my money\nmy cashout is pending")
            ->set('responseText', "Let's check your pending cashouts.")
            ->set('ruleAction', 'show_pending_cashouts')->set('priority', 100)->call('saveRule')
            ->assertHasNoErrors();
        $rule = ChatbotRule::where('name', 'Pending Cashout')->firstOrFail();
        $this->assertSame(['where is my money', 'my cashout is pending'], $rule->trigger_value);
        $before = $this->getConnection()->table('chat_messages')->count();
        $preview = app(ChatbotRuleService::class)->preview($admin, 'where is my money');
        $this->assertSame('Pending Cashout', $preview['name']);
        $this->assertSame('show_pending_cashouts', $preview['action_type']);
        $this->assertSame($before, $this->getConnection()->table('chat_messages')->count());

        app(ChatbotRuleService::class)->updateRule($rule, $admin, ['is_active' => false]);
        $this->assertNull(app(ChatbotRuleService::class)->matchWithoutFallback('where is my money'));
        $this->assertSame(['My Play Request', 'My Deposit', 'My Cashout', 'Brahma Balance', 'Talk to Support', 'Other'],
            ChatbotMenuItem::orderBy('sort_order')->pluck('label')->all());
        $this->assertThrows(fn () => app(ChatbotRuleService::class)->createRule($agent, [
            'name' => 'Denied', 'trigger_type' => 'exact', 'trigger_value' => ['x'], 'action_type' => 'none',
        ]), AuthorizationException::class);
    }

    public function test_channels_keep_stable_identity_enforce_modes_and_per_channel_blocking(): void
    {
        $admin = $this->user('admin');
        $publisher = $this->user('agent');
        $reader = $this->user('agent');
        $service = app(InternalChannelService::class);
        $auth = app(ChatAuthorizationService::class);
        $channel = $service->create($admin, ['name' => 'test-operations', 'channel_description' => 'Ops',
            'channel_mode' => 'restricted'], [$publisher->id]);
        $id = $channel->id;
        $key = $channel->channel_key;
        $auth->assertCanSendInternal($channel, $publisher);
        $this->assertThrows(fn () => $auth->assertCanSendInternal($channel, $reader), AuthorizationException::class);
        app(ChatMessageService::class)->sendInternalMessage($channel, $publisher, 'restricted message');

        $service->block($admin, $channel, $reader);
        $this->assertThrows(fn () => $auth->assertCanViewInternal($channel->fresh(), $reader), AuthorizationException::class);
        $this->assertTrue(app(TeamInboxService::class)->conversations($reader, 'channels')->isEmpty());
        $this->assertSame(0, app(MessengerOverviewService::class)->unreadCount($reader));
        $future = $this->user('agent');
        $service->syncAllStaffWideChannels();
        $this->assertTrue($channel->participants()->where('user_id', $future->id)->whereNull('left_at')->exists());
        $this->assertNotNull($channel->participants()->where('user_id', $reader->id)->value('channel_blocked_at'));
        $service->unblock($admin, $channel, $reader);
        $auth->assertCanViewInternal($channel->fresh(), $reader);
        $this->assertThrows(fn () => $auth->assertCanSendInternal($channel->fresh(), $reader), AuthorizationException::class);

        $renamed = $service->update($admin, $channel, ['name' => 'operations-test', 'channel_description' => 'Renamed',
            'channel_mode' => 'open'], []);
        $this->assertSame($id, $renamed->id);
        $this->assertSame($key, $renamed->channel_key);
        $this->assertSame(1, $renamed->messages()->count());
        $auth->assertCanSendInternal($renamed, $reader);
        $readOnly = $service->update($admin, $renamed, ['name' => 'operations-test', 'channel_description' => 'Renamed',
            'channel_mode' => 'read_only'], []);
        $this->assertThrows(fn () => $auth->assertCanSendInternal($readOnly, $reader), AuthorizationException::class);
        $auth->assertCanSendInternal($readOnly, $admin);
        $service->setArchived($admin, $readOnly, true);
        $this->assertThrows(fn () => $auth->assertCanViewInternal($readOnly->fresh(), $reader), AuthorizationException::class);

        $noticeboard = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        $this->assertSame('read_only', $noticeboard->channel_mode);
        $this->assertThrows(fn () => $service->setArchived($admin, $noticeboard, true), AuthorizationException::class);
        $this->assertSame(1, ChatConversation::where('channel_key', BrahmaNoticeboardService::CHANNEL_KEY)->count());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
