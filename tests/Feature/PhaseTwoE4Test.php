<?php

namespace Tests\Feature;

use App\Livewire\Admin\MessengerBell;
use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatE2eeConversationKey;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\E2eeActivationService;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeDowngradeService;
use App\Services\Chat\E2eeMessageService;
use App\Services\Chat\MessengerOverviewService;
use App\Services\Chat\SupportMessengerOverviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseTwoE4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_closed_messenger_badge_counts_each_plaintext_and_encrypted_message_without_body(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $player = $this->user('player');
        [$direct] = $this->activateDirect($agentA, $agentB);
        app(ConversationService::class)->markInternalConversationRead($direct, $agentB);
        $this->travel(1)->seconds();
        $first = app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);
        $second = app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);

        $group = app(ConversationService::class)->createGroupConversation($agentA, 'Operations', [$agentB, $this->user('agent')]);
        app(ChatMessageService::class)->sendInternalMessage($group, $agentB, 'Own message');
        $this->travel(1)->seconds();
        app(ChatMessageService::class)->sendInternalMessage($group, $agentA, 'Group unread');
        $noticeboard = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        app(ChatMessageService::class)->sendInternalMessage($noticeboard, $admin, 'Notice unread');
        $support = app(ConversationService::class)->getOrCreatePlayerConversation($player);
        app(ChatMessageService::class)->sendPlayerMessage($support, $player, 'Support unread');

        $this->assertNull($first->body);
        $this->assertNull($second->body);
        $this->assertSame(2, app(ConversationService::class)->internalDirectUnreadCount($agentB));
        $this->assertSame(1, app(ConversationService::class)->internalGroupUnreadCount($agentB));
        $this->assertSame(1, app(ConversationService::class)->internalChannelUnreadCount($agentB));
        $this->assertSame(4, app(MessengerOverviewService::class)->unreadCount($agentB));
        $this->assertSame(1, app(SupportMessengerOverviewService::class)->unreadCount($agentB));

        Livewire::actingAs($agentB)->test(MessengerBell::class)
            ->assertSet('unreadCount', 4)
            ->assertSee('4');

        app(ConversationService::class)->markInternalConversationRead($direct, $agentB);
        Livewire::actingAs($agentB)->test(MessengerBell::class)
            ->dispatch('messenger-unread-refresh')
            ->assertSet('unreadCount', 2);

        $bell = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $this->assertStringContainsString('wire:poll.2s="pollUnread"', $bell);
        $this->assertStringContainsString("\$unreadCount > 99 ? '99+'", $bell);
    }

    public function test_plaintext_badge_hides_at_zero_and_caps_only_its_display_above_ninety_nine(): void
    {
        $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);

        Livewire::actingAs($agentB)->test(MessengerBell::class)
            ->assertSet('unreadCount', 0)
            ->assertSeeHtml('data-team-unread-badge')
            ->assertSeeHtml('data-unread="false"')
            ->assertSeeHtml('ring-slate-950 hidden')
            ->assertSeeHtml('>0</span>');

        for ($message = 1; $message <= 100; $message++) {
            app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, "Unread {$message}");
        }

        $this->assertSame(100, app(MessengerOverviewService::class)->unreadCount($agentB));
        Livewire::actingAs($agentB)->test(MessengerBell::class)
            ->assertSet('unreadCount', 100)
            ->assertSee('99+');
    }

    public function test_disable_requires_other_participant_and_preserves_ciphertext_keys_and_boundaries(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $outsider = $this->user('admin');
        [$direct, $devices] = $this->activateDirect($agentA, $agentB);
        $encrypted = app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);
        $keysBefore = ChatE2eeConversationKey::where('conversation_id', $direct->id)->count();
        $downgrade = app(E2eeDowngradeService::class);

        $requested = $downgrade->requestDisable($direct, $agentA);
        $this->assertSame('e2ee_v1', $requested->encryption_mode);
        $this->assertSame($agentA->id, $requested->e2ee_disable_requested_by);
        $this->assertNotNull($requested->e2ee_disable_requested_at);
        $this->assertThrows(fn () => $downgrade->approveDisable($requested, $agentA), AuthorizationException::class);
        $this->assertThrows(fn () => $downgrade->approveDisable($requested, $outsider), AuthorizationException::class);

        $disabled = $downgrade->approveDisable($requested, $agentB);
        $this->assertNull($disabled->encryption_mode);
        $this->assertNotNull($disabled->e2ee_disabled_at);
        $this->assertNull($disabled->e2ee_disable_requested_by);
        $this->assertSame($encrypted->encrypted_payload, $encrypted->fresh()->encrypted_payload);
        $this->assertSame($keysBefore, ChatE2eeConversationKey::where('conversation_id', $direct->id)->count());
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $direct->id,
            'body' => 'End-to-end encryption was disabled for new messages.',
        ]);

        $plaintext = app(ChatMessageService::class)->sendInternalMessage($disabled, $agentA, 'Plaintext after consent');
        $this->assertSame('Plaintext after consent', $plaintext->body);
        $this->assertNull($plaintext->encrypted_payload);

        $wrappedV2 = $devices->map(fn ($device) => $this->wrapped($device->id))->all();
        $reenabled = app(E2eeActivationService::class)->activate($disabled, $agentB, 2, $wrappedV2);
        $this->assertSame('e2ee_v1', $reenabled->encryption_mode);
        $this->assertSame(2, $reenabled->current_key_version);
        $this->assertSame($keysBefore * 2, ChatE2eeConversationKey::where('conversation_id', $direct->id)->count());
    }

    public function test_other_participant_can_keep_encryption_and_duplicate_request_is_idempotent(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $downgrade = app(E2eeDowngradeService::class);

        $first = $downgrade->requestDisable($direct, $agentA);
        $second = $downgrade->requestDisable($first, $agentA);
        $this->assertSame($first->e2ee_disable_requested_at?->toISOString(), $second->e2ee_disable_requested_at?->toISOString());

        $kept = $downgrade->keepEncryption($second, $agentB);
        $this->assertSame('e2ee_v1', $kept->encryption_mode);
        $this->assertNull($kept->e2ee_disable_requested_by);
        $this->assertNull($kept->e2ee_disabled_at);
    }

    public function test_team_delete_uses_application_modal_and_exact_sender_authorization(): void
    {
        $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        $message = app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'Delete me');

        $component = Livewire::actingAs($agentA)->test(TeamMessenger::class, ['initialConversationId' => $direct->id])
            ->call('openDeleteMessage', $message->id)
            ->assertSet('pendingDeleteMessageId', $message->id)
            ->assertSee('Delete message?')
            ->assertSee('Cancel')
            ->assertSee('Delete Message');
        $this->assertNull($message->fresh()->deleted_at);

        $component->call('cancelDeleteMessage')->assertSet('pendingDeleteMessageId', null);
        $this->assertNull($message->fresh()->deleted_at);

        $component->call('openDeleteMessage', $message->id)
            ->call('confirmDeleteMessage')
            ->assertSet('pendingDeleteMessageId', null);
        $this->assertNotNull($message->fresh()->deleted_at);

        $otherMessage = app(ChatMessageService::class)->sendInternalMessage($direct, $agentB, 'Not yours');
        Livewire::actingAs($agentA)->test(TeamMessenger::class, ['initialConversationId' => $direct->id])
            ->call('openDeleteMessage', $otherMessage->id)
            ->assertForbidden();

        $view = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $this->assertStringNotContainsString('wire:confirm=', $view);
        $this->assertStringNotContainsString('confirmDeleteMessage(', $view);
        $this->assertStringContainsString('wire:click="confirmDeleteMessage"', $view);
    }

    public function test_downgrade_state_and_controls_render_for_the_correct_participant(): void
    {
        $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);

        Livewire::actingAs($agentA)->test(TeamMessenger::class, ['initialConversationId' => $direct->id])
            ->assertSee('Disable End-to-End Encryption')
            ->call('openDisableE2ee')
            ->assertSet('showDisableE2eeModal', true)
            ->assertSee('Request Disable');

        app(E2eeDowngradeService::class)->requestDisable($direct, $agentA);
        Livewire::actingAs($agentA)->test(TeamMessenger::class, ['initialConversationId' => $direct->id])
            ->assertSee('Awaiting the other participant');
        Livewire::actingAs($agentB)->test(TeamMessenger::class, ['initialConversationId' => $direct->id])
            ->assertSee('Keep Encryption')
            ->assertSee('Disable Encryption');

        foreach (['e2ee_disable_requested_by', 'e2ee_disable_requested_at', 'e2ee_disabled_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('chat_conversations', $column));
        }
    }

    private function activateDirect(User $a, User $b): array
    {
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($a, $b);
        $devices = collect([
            app(E2eeDeviceService::class)->register($a, $this->devicePayload('A')),
            app(E2eeDeviceService::class)->register($b, $this->devicePayload('B')),
        ]);
        $wrapped = $devices->map(fn ($device) => $this->wrapped($device->id))->all();

        return [app(E2eeActivationService::class)->activate($direct, $a, 1, $wrapped), $devices];
    }

    private function wrapped(int $deviceId): array
    {
        return [
            'device_id' => $deviceId,
            'wrapped_key' => $this->encoded(80),
            'wrapping_algorithm' => 'x25519-sealedbox',
            'format_version' => 1,
        ];
    }

    private function envelope(int $keyVersion = 1): string
    {
        return json_encode([
            'v' => 1,
            'alg' => 'xchacha20poly1305-ietf',
            'key_version' => $keyVersion,
            'nonce' => $this->encoded(24),
            'ciphertext' => $this->encoded(48),
        ], JSON_THROW_ON_ERROR);
    }

    private function devicePayload(string $name): array
    {
        return [
            'device_uuid' => $this->encoded(16),
            'device_name' => $name,
            'public_encryption_key' => $this->encoded(32),
            'public_signing_key' => $this->encoded(32),
            'key_fingerprint' => $this->encoded(32),
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function encoded(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
