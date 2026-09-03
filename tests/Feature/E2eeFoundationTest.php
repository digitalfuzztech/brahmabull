<?php

namespace Tests\Feature;

use App\Models\ChatConversationParticipant;
use App\Models\ChatE2eeDevice;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\E2eeAuthorizationService;
use App\Services\Chat\E2eeConversationKeyService;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeEnvelopeValidator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class E2eeFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_device_registration_is_staff_owned_validated_and_first_device_only_is_trusted(): void
    {
        $agent = $this->user('agent');
        $admin = $this->user('admin');
        $player = $this->user('player');
        $payload = $this->devicePayload('Office Browser');

        $this->actingAs($agent)->postJson(route('team.e2ee.devices.store'), $payload)
            ->assertCreated()
            ->assertJsonMissing(['private_key']);
        $device = ChatE2eeDevice::firstOrFail();
        $this->assertSame($agent->id, $device->user_id);
        $this->assertNotNull($device->trusted_at);
        $this->assertNull($device->revoked_at);

        $this->actingAs($agent)->postJson(route('team.e2ee.devices.store'), $payload)
            ->assertOk();
        $this->assertSame(1, ChatE2eeDevice::count());

        $secondPayload = $this->devicePayload('Home Browser');
        $this->actingAs($agent)->postJson(route('team.e2ee.devices.store'), $secondPayload)
            ->assertCreated();
        $this->assertNull(ChatE2eeDevice::where('device_uuid', $secondPayload['device_uuid'])->value('trusted_at'));

        $this->actingAs($admin)->postJson(route('team.e2ee.devices.store'), $this->devicePayload('Admin Browser'))
            ->assertCreated();
        $this->actingAs($player)->postJson(route('team.e2ee.devices.store'), $this->devicePayload())
            ->assertForbidden();
        auth()->logout();
        $this->postJson(route('team.e2ee.devices.store'), $this->devicePayload())->assertUnauthorized();

        $this->actingAs($agent)->postJson(route('team.e2ee.devices.store'), $this->devicePayload() + [
            'user_id' => $admin->id,
            'private_encryption_key' => $this->encoded(32),
        ])->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'private_encryption_key']);
        $this->actingAs($agent)->postJson(route('team.e2ee.devices.store'), array_diff_key($this->devicePayload(), ['public_encryption_key' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('public_encryption_key');
        $this->assertArrayNotHasKey('private_key', $device->getAttributes());
    }

    public function test_device_revocation_is_owner_scoped_and_preserves_historical_record(): void
    {
        $owner = $this->user('agent');
        $other = $this->user('agent');
        $device = app(E2eeDeviceService::class)->register($owner, $this->devicePayload());

        $this->assertThrows(fn () => app(E2eeDeviceService::class)->revoke($other, $device), AuthorizationException::class);
        $revoked = app(E2eeDeviceService::class)->revoke($owner, $device);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertDatabaseHas('chat_e2ee_devices', ['id' => $device->id]);
        $this->assertFalse($revoked->isTrustedAndActive());
    }

    public function test_public_device_directory_is_limited_to_the_two_direct_participants(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $unrelated = $this->user('agent');
        $devices = app(E2eeDeviceService::class);
        $devices->register($agentA, $this->devicePayload('A'));
        $devices->register($agentB, $this->devicePayload('B'));
        $devices->register($admin, $this->devicePayload('Admin'));
        $devices->register($unrelated, $this->devicePayload('Unrelated'));
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        $directory = $devices->publicDevicesForConversation($direct, $agentA);

        $this->assertEqualsCanonicalizing([$agentA->id, $agentB->id], $directory->pluck('user_id')->all());
        $this->assertArrayNotHasKey('private_encryption_key', $directory->first());
        $this->assertThrows(fn () => $devices->publicDevicesForConversation($direct, $unrelated), AuthorizationException::class);
        $this->assertThrows(fn () => $devices->publicDevicesForConversation($direct, $admin), AuthorizationException::class);
        $this->actingAs($admin)->getJson(route('team.e2ee.conversations.devices', $direct))->assertForbidden();

        $adminDirect = app(ConversationService::class)->getOrCreateDirectConversation($admin, $agentA);
        $this->assertEqualsCanonicalizing(
            [$admin->id, $agentA->id],
            $devices->publicDevicesForConversation($adminDirect, $admin)->pluck('user_id')->all(),
        );
    }

    public function test_e2ee_metadata_is_rejected_outside_exact_internal_direct_conversations(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $player = $this->user('player');
        $conversations = app(ConversationService::class);
        $authorization = app(E2eeAuthorizationService::class);
        $direct = $conversations->getOrCreateDirectConversation($agentA, $agentB);
        $support = $conversations->getOrCreatePlayerConversation($player);
        $group = $conversations->createGroupConversation($agentA, 'Group', [$agentB, $this->user('agent')]);
        $noticeboard = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();

        $this->assertSame([$agentA->id, $agentB->id], $authorization->assertDirectParticipant($direct, $agentA));
        foreach ([$support, $group, $noticeboard] as $invalid) {
            $this->assertThrows(fn () => $authorization->assertDirectParticipant($invalid, $admin), AuthorizationException::class);
        }

        ChatConversationParticipant::create([
            'conversation_id' => $direct->id,
            'user_id' => $admin->id,
            'participant_role' => 'member',
            'joined_at' => now(),
        ]);
        $this->assertThrows(fn () => $authorization->assertDirectParticipant($direct, $agentA), AuthorizationException::class);
    }

    public function test_wrapped_keys_are_opaque_participant_scoped_immutable_and_versioned(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $outsider = $this->user('agent');
        $devices = app(E2eeDeviceService::class);
        $deviceA = $devices->register($agentA, $this->devicePayload('A'));
        $deviceB = $devices->register($agentB, $this->devicePayload('B'));
        $outsiderDevice = $devices->register($outsider, $this->devicePayload('X'));
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        $keys = app(E2eeConversationKeyService::class);
        $wrappedA = $this->encoded(80);
        $wrappedB = $this->encoded(80);

        $first = $keys->storeWrappedKey($direct, $agentA, $deviceA, 1, $wrappedA);
        $this->assertSame($wrappedA, $first->wrapped_key);
        $this->actingAs($agentA)->postJson(route('team.e2ee.conversations.keys.store', $direct), [
            'device_id' => $deviceA->id,
            'key_version' => 1,
            'wrapped_key' => $wrappedA,
            'wrapping_algorithm' => E2eeConversationKeyService::WRAPPING_ALGORITHM,
            'format_version' => E2eeConversationKeyService::FORMAT_VERSION,
        ])->assertOk()->assertJsonMissing(['wrapped_key' => $wrappedA]);
        $this->assertSame($first->id, $keys->storeWrappedKey($direct, $agentA, $deviceA, 1, $wrappedA)->id);
        $this->assertThrows(fn () => $keys->storeWrappedKey($direct, $agentA, $deviceA, 1, $this->encoded(80)), DomainException::class);
        $this->assertSame(1, $keys->storeWrappedKey($direct, $agentA, $deviceB, 1, $wrappedB)->key_version);
        $this->assertThrows(fn () => $keys->storeWrappedKey($direct, $agentA, $outsiderDevice, 1, $this->encoded(80)), AuthorizationException::class);

        $devices->revoke($agentB, $deviceB);
        $this->assertThrows(fn () => $keys->storeWrappedKey($direct, $agentA, $deviceB->fresh(), 2, $this->encoded(80)), AuthorizationException::class);
        $this->assertSame(2, $keys->nextProvisioningVersion($direct, $agentA));
        $this->assertSame(2, $keys->storeWrappedKey($direct, $agentA, $deviceA, 2, $this->encoded(80))->key_version);
        $this->assertThrows(fn () => $keys->storeWrappedKey($direct, $agentA, $deviceA, 1, $wrappedA), DomainException::class);
        $this->assertThrows(fn () => $keys->storeWrappedKey($direct, $agentA, $deviceA, 4, $this->encoded(80)), DomainException::class);

        $this->assertNull($direct->fresh()->encryption_mode);
        $this->assertNull($direct->fresh()->current_key_version);
        $this->assertFalse(method_exists($keys, 'decrypt'));
        $this->assertFalse(method_exists($keys, 'unwrap'));
    }

    public function test_encrypted_envelope_validation_is_strict_without_decryption(): void
    {
        $validator = app(E2eeEnvelopeValidator::class);
        $valid = json_encode([
            'v' => 1,
            'alg' => 'xchacha20poly1305-ietf',
            'key_version' => 1,
            'nonce' => $this->encoded(24),
            'ciphertext' => $this->encoded(48),
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(1, $validator->validate($valid)['v']);
        $this->assertThrows(fn () => $validator->validate(str_replace('xchacha20poly1305-ietf', 'aes', $valid)), DomainException::class);
        $this->assertThrows(fn () => $validator->validate(str_replace('"v":1', '"v":2', $valid)), DomainException::class);
        $this->assertThrows(fn () => $validator->validate('{bad json'), DomainException::class);
        $this->assertThrows(fn () => $validator->validate(json_encode([
            'v' => 1,
            'alg' => 'xchacha20poly1305-ietf',
            'key_version' => 1,
            'nonce' => 'not+base64',
            'ciphertext' => $this->encoded(48),
        ], JSON_THROW_ON_ERROR)), DomainException::class);
        $this->assertThrows(fn () => $validator->validate(str_repeat('x', E2eeEnvelopeValidator::MAX_ENVELOPE_BYTES + 1)), DomainException::class);
    }

    public function test_schema_is_dormant_and_existing_plaintext_behavior_is_unchanged(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentB);
        $message = app(ChatMessageService::class)->sendInternalMessage($direct, $agentA, 'Existing plaintext path');

        $this->assertTrue(Schema::hasTable('chat_e2ee_devices'));
        $this->assertTrue(Schema::hasTable('chat_e2ee_conversation_keys'));
        foreach (['encryption_mode', 'e2ee_enabled_at', 'current_key_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('chat_conversations', $column));
        }
        foreach (['client_message_uuid', 'encrypted_payload', 'encryption_version', 'key_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('chat_messages', $column));
        }

        $this->assertSame('Existing plaintext path', $message->fresh()->body);
        $this->assertNull($message->fresh()->encrypted_payload);
        $this->assertNull($message->fresh()->encryption_version);
        $this->assertNull($message->fresh()->client_message_uuid);
        $this->assertNull($direct->fresh()->encryption_mode);
        $this->assertNull($direct->fresh()->e2ee_enabled_at);
        $this->assertSame(0, ChatMessage::whereNotNull('encrypted_payload')->count());
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
