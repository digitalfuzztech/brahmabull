<?php

namespace Tests\Feature;

use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatConversation;
use App\Models\ChatE2eeConversationKey;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\E2eeActivationService;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeMessageService;
use App\Services\Chat\MessengerOverviewService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class E2eeDirectActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_activation_is_explicit_direct_only_and_requires_both_participants_trusted_devices(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $this->user('admin');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $direct = $conversations->getOrCreateDirectConversation($agentA, $agentB);
        $legacy = app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'Legacy plaintext');

        $this->assertNull($direct->fresh()->encryption_mode);
        $this->assertSame('Legacy plaintext', $legacy->fresh()->body);
        $deviceA = app(E2eeDeviceService::class)->register($agentA, $this->devicePayload('A'));
        $this->assertThrows(
            fn () => app(E2eeActivationService::class)->activate($direct, $agentA, 1, [$this->wrapped($deviceA->id)]),
            DomainException::class,
        );
        $this->assertNull($direct->fresh()->encryption_mode);

        $support = $conversations->getOrCreatePlayerConversation($player);
        $group = $conversations->createGroupConversation($agentA, 'Group', [$agentB, $this->user('agent')]);
        $channel = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        foreach ([$support, $group, $channel] as $invalid) {
            $this->assertThrows(
                fn () => app(E2eeActivationService::class)->activate($invalid, $agentA, 1, []),
                AuthorizationException::class,
            );
        }
    }

    public function test_agent_and_admin_direct_activation_is_complete_idempotent_and_race_safe(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $admin = $this->user('admin');
        [$direct, $devices, $wrapped] = $this->preparedDirect($agentA, $agentB);
        $activated = app(E2eeActivationService::class)->activate($direct, $agentA, 1, $wrapped);

        $this->assertSame('e2ee_v1', $activated->encryption_mode);
        $this->assertSame(1, $activated->current_key_version);
        $this->assertNotNull($activated->e2ee_enabled_at);
        $this->assertEqualsCanonicalizing($devices->pluck('id')->all(), ChatE2eeConversationKey::pluck('device_id')->all());
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $direct->id,
            'sender_type' => 'system',
            'body' => 'End-to-end encryption was enabled for new messages.',
        ]);

        $authoritative = ChatE2eeConversationKey::orderBy('device_id')->pluck('wrapped_key')->all();
        app(E2eeActivationService::class)->activate($direct->fresh(), $agentB, 1, [
            $this->wrapped($devices[0]->id, $this->encoded(80)),
            $this->wrapped($devices[1]->id, $this->encoded(80)),
        ]);
        $this->assertSame($authoritative, ChatE2eeConversationKey::orderBy('device_id')->pluck('wrapped_key')->all());
        $this->assertSame(1, ChatMessage::where('conversation_id', $direct->id)->whereJsonContains('metadata->e2ee_boundary', true)->count());

        [$adminDirect] = $this->preparedDirect($admin, $agentA);
        $this->assertSame('e2ee_v1', app(E2eeActivationService::class)->activate(
            $adminDirect,
            $admin,
            1,
            $this->wrappedForConversation($adminDirect),
        )->encryption_mode);
    }

    public function test_partial_or_revoked_device_provisioning_never_activates(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct, $devices] = $this->preparedDirect($agentA, $agentB);

        $this->assertThrows(
            fn () => app(E2eeActivationService::class)->activate($direct, $agentA, 1, [$this->wrapped($devices[0]->id)]),
            DomainException::class,
        );
        $this->assertNull($direct->fresh()->encryption_mode);
        $this->assertSame(0, ChatE2eeConversationKey::count());

        app(E2eeDeviceService::class)->revoke($agentB, $devices[1]);
        $this->assertThrows(
            fn () => app(E2eeActivationService::class)->activate($direct, $agentA, 1, [$this->wrapped($devices[0]->id)]),
            DomainException::class,
        );
        $this->assertNull($direct->fresh()->encryption_mode);
    }

    public function test_activated_direct_accepts_ciphertext_only_and_validates_uuid_version_reply_and_participant(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $outsider = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $uuid = fake()->uuid();
        $message = app(E2eeMessageService::class)->send($direct, $agentA, $uuid, $this->envelope(), 1, 1);

        $this->assertNull($message->body);
        $this->assertNotNull($message->encrypted_payload);
        $this->assertSame($uuid, $message->client_message_uuid);
        $this->assertThrows(fn () => app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'leak'), DomainException::class);
        $this->assertThrows(fn () => app(E2eeMessageService::class)->send($direct, $agentA, $uuid, $this->envelope(), 1, 1), DomainException::class);
        $this->assertThrows(fn () => app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(2), 1, 2), DomainException::class);
        $this->assertThrows(fn () => app(E2eeMessageService::class)->send($direct, $outsider, fake()->uuid(), $this->envelope(), 1, 1), AuthorizationException::class);

        $other = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $outsider);
        $this->assertThrows(
            fn () => app(E2eeMessageService::class)->send($direct, $agentB, fake()->uuid(), $this->envelope(), 1, 1, app(ChatMessageService::class)->sendInternalMessage($other, $agentA, 'Other')->id),
            ModelNotFoundException::class,
        );

        $this->actingAs($agentA)->postJson(route('team.e2ee.conversations.messages.store', $direct), [
            'client_message_uuid' => fake()->uuid(),
            'encrypted_payload' => $this->envelope(),
            'encryption_version' => 1,
            'key_version' => 1,
            'body' => 'must never arrive',
        ])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_encrypted_reply_edit_and_delete_never_require_plaintext(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $parent = app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);
        $reply = app(E2eeMessageService::class)->send($direct, $agentB, fake()->uuid(), $this->envelope(), 1, 1, $parent->id);

        $this->assertSame($parent->id, $reply->reply_to_message_id);
        $this->assertNull($reply->body);
        $edited = app(E2eeMessageService::class)->edit($parent, $agentA, $this->envelope(), 1, 1);
        $this->assertNotNull($edited->edited_at);
        $this->assertNull($edited->body);
        $this->assertThrows(fn () => app(E2eeMessageService::class)->edit($parent, $agentB, $this->envelope(), 1, 1), AuthorizationException::class);

        $deleted = app(ChatMessageService::class)->deleteInternalMessage($parent, $agentA);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertNull($deleted->encrypted_payload);
        $this->assertDatabaseHas('chat_messages', ['id' => $reply->id, 'reply_to_message_id' => $parent->id]);
        $this->assertThrows(fn () => app(ChatMessageService::class)->deleteInternalMessage($reply, $agentA), AuthorizationException::class);
    }

    public function test_e2ee_direct_rejects_plaintext_attachment_and_reaction_paths_without_changing_plaintext_or_group_behavior(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $agentC = $this->user('agent');
        [$encrypted] = $this->activateDirect($agentA, $agentB);
        $encryptedMessage = app(E2eeMessageService::class)->send($encrypted, $agentA, fake()->uuid(), $this->envelope(), 1, 1);

        $this->assertThrows(fn () => app(ChatMessageService::class)->addReaction($encryptedMessage, $agentB, '👍'), AuthorizationException::class);
        $this->assertThrows(fn () => app(ChatAttachmentService::class)->sendWithUpload(
            $encrypted,
            $agentA,
            UploadedFile::fake()->image('secret.jpg'),
        ), AuthorizationException::class);

        $plain = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentC);
        $plainMessage = app(ChatMessageService::class)->sendInternalMessage($plain, $agentA, 'Plain still works');
        $this->assertSame('👍', app(ChatMessageService::class)->addReaction($plainMessage, $agentC, '👍')->reaction);
        $group = app(ConversationService::class)->createGroupConversation($agentA, 'Group', [$agentB, $agentC]);
        $groupMessage = app(ChatMessageService::class)->sendInternalMessage($group, $agentA, 'Group unchanged');
        $this->assertSame('👍', app(ChatMessageService::class)->addReaction($groupMessage, $agentB, '👍')->reaction);
    }

    public function test_encrypted_unread_and_previews_are_metadata_only(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        app(ConversationService::class)->markInternalConversationRead($direct, $agentB);
        $this->travel(1)->seconds();
        $message = app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);

        $this->assertSame(1, app(MessengerOverviewService::class)->unreadCount($agentB));
        $row = app(MessengerOverviewService::class)->recent($agentB)->firstWhere('id', $direct->id);
        $this->assertSame('New encrypted message', $row['preview']);
        $this->assertStringNotContainsString($message->encrypted_payload, $row['preview']);

        app(ChatMessageService::class)->deleteInternalMessage($message, $agentA);
        $this->assertSame('Message deleted', app(MessengerOverviewService::class)->recent($agentB)->firstWhere('id', $direct->id)['preview']);
    }

    public function test_revoking_a_provisioned_device_pauses_sending_until_future_rotation(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct, $devices] = $this->activateDirect($agentA, $agentB);
        app(E2eeDeviceService::class)->revoke($agentB, $devices[1]);

        $this->assertThrows(
            fn () => app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1),
            DomainException::class,
        );
        $this->assertSame(0, ChatMessage::where('conversation_id', $direct->id)->whereNotNull('encrypted_payload')->count());
    }

    public function test_activated_direct_uses_browser_local_composer_and_client_encrypted_attachment_and_reaction_ui(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $this->user('admin');
        [$direct] = $this->activateDirect($agentA, $agentB);
        app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);

        Livewire::actingAs($agentA)->test(TeamMessenger::class)
            ->call('selectTeamConversation', $direct->id)
            ->assertSeeHtml('x-model="draft"')
            ->assertSeeHtml('x-on:keydown.enter=')
            ->assertSeeHtml('x-ref="encryptedAttachment"')
            ->assertSeeHtml('x-on:click="react(')
            ->assertDontSeeHtml('wire:model="message"')
            ->assertDontSeeHtml('wire:model="attachment"');
    }

    private function activateDirect(User $a, User $b): array
    {
        [$direct, $devices, $wrapped] = $this->preparedDirect($a, $b);
        app(E2eeActivationService::class)->activate($direct, $a, 1, $wrapped);

        return [$direct->fresh(), $devices];
    }

    private function preparedDirect(User $a, User $b): array
    {
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($a, $b);
        $devices = collect([
            app(E2eeDeviceService::class)->register($a, $this->devicePayload('A')),
            app(E2eeDeviceService::class)->register($b, $this->devicePayload('B')),
        ]);

        return [$direct, $devices, $devices->map(fn ($device) => $this->wrapped($device->id))->all()];
    }

    private function wrappedForConversation(ChatConversation $conversation): array
    {
        return $conversation->activeParticipants()->with('user')->get()
            ->flatMap(fn ($participant) => $participant->user->chatE2eeDevices)
            ->filter->isTrustedAndActive()
            ->map(fn ($device) => $this->wrapped($device->id))
            ->values()->all();
    }

    private function wrapped(int $deviceId, ?string $key = null): array
    {
        return [
            'device_id' => $deviceId,
            'wrapped_key' => $key ?? $this->encoded(80),
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

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function devicePayload(?string $name = null): array
    {
        return [
            'device_uuid' => $this->encoded(16),
            'device_name' => $name,
            'public_encryption_key' => $this->encoded(32),
            'public_signing_key' => $this->encoded(32),
            'key_fingerprint' => $this->encoded(32),
        ];
    }

    private function encoded(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
