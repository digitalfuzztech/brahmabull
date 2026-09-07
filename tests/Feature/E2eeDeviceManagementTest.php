<?php

namespace Tests\Feature;

use App\Models\ChatE2eeDevice;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\E2eeActivationService;
use App\Services\Chat\E2eeConversationKeyService;
use App\Services\Chat\E2eeDeviceProofService;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeDeviceTrustService;
use App\Services\Chat\E2eeMessageService;
use App\Services\Chat\E2eeReactionService;
use App\Services\Chat\E2eeRotationService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class E2eeDeviceManagementTest extends TestCase
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

    public function test_device_management_lists_only_own_public_metadata_and_staff_only_ui(): void
    {
        $agent = $this->user('agent');
        $other = $this->user('agent');
        $player = $this->user('player');
        $agentDevice = $this->register($agent, $this->material('Office'));
        $second = $this->register($agent, $this->material('Laptop'));
        $otherDevice = $this->register($other, $this->material('Other'));
        app(E2eeDeviceService::class)->revoke($agent, $second);

        $this->actingAs($agent)->getJson(route('team.e2ee.devices.index'))
            ->assertOk()
            ->assertJsonCount(2, 'devices')
            ->assertJsonFragment(['device_uuid' => $agentDevice->device_uuid])
            ->assertJsonMissing(['device_uuid' => $otherDevice->device_uuid])
            ->assertJsonMissing(['private_key']);
        $this->actingAs($player)->getJson(route('team.e2ee.devices.index'))->assertForbidden();
        auth()->logout();
        $this->getJson(route('team.e2ee.devices.index'))->assertUnauthorized();

        $view = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $this->assertStringContainsString('Secure Chat Devices', $view);
        $this->assertStringContainsString('This device', $view);
        $this->assertStringContainsString('This device is awaiting approval.', $view);
        $this->assertStringContainsString('Open Secure Devices from one of your trusted devices to approve this browser.', $view);
        $this->assertStringContainsString('Approval required', file_get_contents(resource_path('js/chat/e2ee/team-messenger.js')));
        $this->assertTrue(Schema::hasColumn('chat_conversations', 'e2ee_rotation_required_at'));
    }

    public function test_trusted_device_cryptographically_approves_same_user_device_after_complete_current_key_provisioning(): void
    {
        $agent = $this->user('agent');
        $peer = $this->user('agent');
        $admin = $this->user('admin');
        $approverMaterial = $this->material('Trusted');
        $targetMaterial = $this->material('New');
        $approver = $this->register($agent, $approverMaterial);
        $target = $this->register($agent, $targetMaterial);
        $peerDevice = $this->register($peer, $this->material('Peer'));
        $adminDevice = $this->register($admin, $this->material('Admin'));
        $conversation = app(ConversationService::class)->getOrCreateDirectConversation($agent, $peer);
        app(E2eeActivationService::class)->activate($conversation, $agent, 1, [
            $this->wrapped($approver->id, 1),
            $this->wrapped($peerDevice->id, 1),
        ]);

        $this->assertNull($target->trusted_at);
        $plan = $this->actingAs($agent)->getJson(route('team.e2ee.devices.approval-plan', [
            'device' => $target,
            'approver_device_uuid' => $approver->device_uuid,
        ]))->assertOk()->json();
        $provisioning = [[
            'conversation_id' => $conversation->id,
            'key_version' => 1,
            'wrapped_key' => $this->encoded(random_bytes(80)),
            'wrapping_algorithm' => 'x25519-sealedbox',
            'format_version' => 1,
        ]];
        $canonical = app(E2eeDeviceProofService::class)->approvalCanonical(
            $agent->id,
            $approver->id,
            $target->id,
            $plan['challenge'],
            $provisioning,
        );
        $signature = $this->encoded(sodium_crypto_sign_detached($canonical, $approverMaterial['signing_secret']));
        $this->postJson(route('team.e2ee.devices.approve', $target), [
            'approver_device_uuid' => $approver->device_uuid,
            'challenge' => $plan['challenge'],
            'provisioning' => $provisioning,
            'signature' => $signature,
        ])->assertOk();
        $approved = $target->fresh();

        $this->assertNotNull($approved->trusted_at);
        $this->assertDatabaseHas('chat_e2ee_conversation_keys', [
            'conversation_id' => $conversation->id,
            'device_id' => $target->id,
            'key_version' => 1,
        ]);
        $this->assertSame(1, app(E2eeConversationKeyService::class)
            ->wrappedKeyForOwnDevice($conversation->fresh(), $agent, $target->device_uuid)['key_version']);
        $this->assertSame($approved->id, app(E2eeDeviceTrustService::class)->approve(
            $agent,
            $approved,
            $approver->device_uuid,
            'already-approved-is-idempotent',
            [],
            'not-used',
        )->id);
        $this->assertThrows(
            fn () => app(E2eeDeviceTrustService::class)->approvalPlan($admin, $target, $adminDevice->device_uuid),
            AuthorizationException::class,
        );
        $this->assertArrayNotHasKey('private_key', $plan['target_device']);
        $this->assertFalse(method_exists(E2eeDeviceTrustService::class, 'decrypt'));
    }

    public function test_untrusted_revoked_cross_user_and_incomplete_approvals_are_denied(): void
    {
        $agent = $this->user('agent');
        $peer = $this->user('agent');
        $trustedMaterial = $this->material('Trusted');
        $trusted = $this->register($agent, $trustedMaterial);
        $target = $this->register($agent, $this->material('Target'));
        $untrusted = $this->register($agent, $this->material('Untrusted'));
        $peerDevice = $this->register($peer, $this->material('Peer'));
        $conversation = app(ConversationService::class)->getOrCreateDirectConversation($agent, $peer);
        app(E2eeActivationService::class)->activate($conversation, $agent, 1, [
            $this->wrapped($trusted->id, 1),
            $this->wrapped($peerDevice->id, 1),
        ]);

        $this->assertThrows(
            fn () => app(E2eeDeviceTrustService::class)->approvalPlan($agent, $target, $untrusted->device_uuid),
            ModelNotFoundException::class,
        );
        $plan = app(E2eeDeviceTrustService::class)->approvalPlan($agent, $target, $trusted->device_uuid);
        $canonical = app(E2eeDeviceProofService::class)->approvalCanonical(
            $agent->id,
            $trusted->id,
            $target->id,
            $plan['challenge'],
            [],
        );
        $signature = $this->encoded(sodium_crypto_sign_detached($canonical, $trustedMaterial['signing_secret']));
        $this->assertThrows(
            fn () => app(E2eeDeviceTrustService::class)->approve($agent, $target, $trusted->device_uuid, $plan['challenge'], [], $signature),
            DomainException::class,
        );
        $this->assertNull($target->fresh()->trusted_at);

        $complete = [[
            'conversation_id' => $conversation->id,
            'key_version' => 1,
            'wrapped_key' => $this->encoded(random_bytes(80)),
            'wrapping_algorithm' => 'x25519-sealedbox',
            'format_version' => 1,
        ]];
        $this->assertThrows(
            fn () => app(E2eeDeviceTrustService::class)->approve(
                $agent,
                $target,
                $trusted->device_uuid,
                $plan['challenge'],
                $complete,
                $this->encoded(random_bytes(SODIUM_CRYPTO_SIGN_BYTES)),
            ),
            DomainException::class,
        );
        $this->assertNull($target->fresh()->trusted_at);

        app(E2eeDeviceService::class)->revoke($agent, $trusted);
        $this->assertThrows(
            fn () => app(E2eeDeviceTrustService::class)->approvalPlan($agent, $target, $trusted->device_uuid),
            ModelNotFoundException::class,
        );
        $this->assertThrows(fn () => app(E2eeDeviceService::class)->revoke($peer, $target), AuthorizationException::class);
    }

    public function test_revocation_blocks_sending_and_signed_rotation_advances_once_without_revoked_device(): void
    {
        $agent = $this->user('agent');
        $peer = $this->user('agent');
        $outsider = $this->user('admin');
        $agentMaterial = $this->material('Agent');
        $agentDevice = $this->register($agent, $agentMaterial);
        $revokedDevice = $this->register($agent, $this->material('Old'));
        $revokedDevice->update(['trusted_at' => now()]);
        $peerDevice = $this->register($peer, $this->material('Peer'));
        $outsiderDevice = $this->register($outsider, $this->material('Admin'));
        $conversation = app(ConversationService::class)->getOrCreateDirectConversation($agent, $peer);
        app(E2eeActivationService::class)->activate($conversation, $agent, 1, [
            $this->wrapped($agentDevice->id, 1),
            $this->wrapped($revokedDevice->id, 1),
            $this->wrapped($peerDevice->id, 1),
        ]);

        app(E2eeDeviceService::class)->revoke($agent, $revokedDevice);
        $this->assertNotNull($conversation->fresh()->e2ee_rotation_required_at);
        $this->assertThrows(
            fn () => app(E2eeMessageService::class)->send($conversation->fresh(), $agent, fake()->uuid(), $this->envelope(1), 1, 1),
            DomainException::class,
        );

        $wrapped = [$this->wrapped($agentDevice->id, 2), $this->wrapped($peerDevice->id, 2)];
        $this->assertThrows(
            fn () => app(E2eeRotationService::class)->rotate($conversation->fresh(), $agent, $agentDevice->device_uuid, 3, $wrapped, ''),
            DomainException::class,
        );
        $this->assertThrows(
            fn () => app(E2eeRotationService::class)->rotate($conversation->fresh(), $agent, $agentDevice->device_uuid, 2, [$wrapped[0]], ''),
            DomainException::class,
        );
        $canonical = app(E2eeDeviceProofService::class)->rotationCanonical(
            $agent->id,
            $agentDevice->id,
            $conversation->id,
            2,
            $wrapped,
        );
        $signature = $this->encoded(sodium_crypto_sign_detached($canonical, $agentMaterial['signing_secret']));
        $this->actingAs($agent)->postJson(route('team.e2ee.conversations.rotate', $conversation), [
            'initiator_device_uuid' => $agentDevice->device_uuid,
            'key_version' => 2,
            'wrapped_keys' => $wrapped,
            'signature' => $signature,
        ])->assertOk()->assertJsonPath('conversation.current_key_version', 2);
        $rotated = $conversation->fresh();

        $this->assertSame(2, $rotated->current_key_version);
        $this->assertNull($rotated->e2ee_rotation_required_at);
        $this->assertDatabaseHas('chat_e2ee_conversation_keys', ['conversation_id' => $conversation->id, 'device_id' => $agentDevice->id, 'key_version' => 1]);
        $this->assertDatabaseMissing('chat_e2ee_conversation_keys', ['conversation_id' => $conversation->id, 'device_id' => $revokedDevice->id, 'key_version' => 2]);
        $this->assertSame(1, app(E2eeConversationKeyService::class)
            ->wrappedKeyForOwnDevice($rotated, $agent, $agentDevice->device_uuid, 1)['key_version']);
        $this->assertSame(2, app(E2eeConversationKeyService::class)
            ->wrappedKeyForOwnDevice($rotated, $agent, $agentDevice->device_uuid, 2)['key_version']);
        $message = app(E2eeMessageService::class)->send($rotated, $agent, fake()->uuid(), $this->envelope(2), 1, 2);
        $this->assertSame(2, $message->key_version);
        $this->assertSame(2, app(E2eeMessageService::class)->send(
            $rotated,
            $peer,
            fake()->uuid(),
            $this->envelope(2),
            1,
            2,
        )->key_version);
        $edited = app(E2eeMessageService::class)->edit($message, $agent, $this->envelope(2), 1, 2);
        $this->assertNotNull($edited->edited_at);
        $reaction = app(E2eeReactionService::class)->store($message, $peer, $this->envelope(2), 1, 2);
        $this->assertSame(2, $reaction->key_version);
        $attachment = app(ChatAttachmentService::class)->sendEncryptedUpload(
            $rotated,
            $agent,
            UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(64)),
            [
                'client_message_uuid' => fake()->uuid(),
                'client_attachment_uuid' => fake()->uuid(),
                'encrypted_payload' => $this->envelope(2),
                'encrypted_key' => $this->envelope(2),
                'encrypted_metadata' => $this->envelope(2),
                'encryption_version' => 1,
                'key_version' => 2,
                'reply_to_message_id' => null,
            ],
        );
        $this->assertSame(2, $attachment->key_version);
        $this->assertThrows(
            fn () => app(E2eeRotationService::class)->rotate($rotated, $agent, $agentDevice->device_uuid, 2, $wrapped, $signature),
            DomainException::class,
        );
        $this->assertThrows(
            fn () => app(E2eeRotationService::class)->rotate($conversation->fresh(), $outsider, $outsiderDevice->device_uuid, 3, [], ''),
            AuthorizationException::class,
        );
        $this->assertThrows(
            fn () => app(E2eeConversationKeyService::class)->wrappedKeyForOwnDevice($rotated, $agent, $revokedDevice->device_uuid, 2),
            ModelNotFoundException::class,
        );
    }

    public function test_first_device_bootstrap_remains_single_under_serialized_registration(): void
    {
        $agent = $this->user('agent');
        $first = $this->register($agent, $this->material('First'));
        $second = $this->register($agent, $this->material('Second'));

        $this->assertNotNull($first->trusted_at);
        $this->assertNull($second->trusted_at);
        $this->assertSame(1, ChatE2eeDevice::where('user_id', $agent->id)->whereNotNull('trusted_at')->count());
    }

    private function register(User $user, array $material): ChatE2eeDevice
    {
        return app(E2eeDeviceService::class)->register($user, $material['payload']);
    }

    private function material(string $name): array
    {
        $encryptionPair = sodium_crypto_box_keypair();
        $signingPair = sodium_crypto_sign_keypair();
        $publicEncryption = sodium_crypto_box_publickey($encryptionPair);
        $publicSigning = sodium_crypto_sign_publickey($signingPair);

        return [
            'payload' => [
                'device_uuid' => $this->encoded(random_bytes(16)),
                'device_name' => $name,
                'public_encryption_key' => $this->encoded($publicEncryption),
                'public_signing_key' => $this->encoded($publicSigning),
                'key_fingerprint' => $this->encoded(sodium_crypto_generichash($publicEncryption.$publicSigning, '', 32)),
            ],
            'signing_secret' => sodium_crypto_sign_secretkey($signingPair),
        ];
    }

    private function wrapped(int $deviceId, int $version): array
    {
        return [
            'device_id' => $deviceId,
            'wrapped_key' => $this->encoded(random_bytes(80)),
            'wrapping_algorithm' => 'x25519-sealedbox',
            'format_version' => 1,
        ];
    }

    private function envelope(int $keyVersion): string
    {
        return json_encode([
            'v' => 1,
            'alg' => 'xchacha20poly1305-ietf',
            'key_version' => $keyVersion,
            'nonce' => $this->encoded(random_bytes(24)),
            'ciphertext' => $this->encoded(random_bytes(32)),
        ], JSON_THROW_ON_ERROR);
    }

    private function encoded(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
