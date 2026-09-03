<?php

namespace Tests\Feature;

use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageReaction;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\E2eeActivationService;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeMessageService;
use App\Services\Chat\E2eeReactionService;
use App\Services\Chat\MessengerOverviewService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class E2eeAttachmentsReactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_participant_uploads_only_ciphertext_with_opaque_private_metadata(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $plaintext = 'recognizable-private-file-bytes';
        $ciphertext = random_bytes(128);

        $response = $this->actingAs($agentA)->post(route('team.e2ee.conversations.attachments.store', $direct), [
            ...$this->attachmentPayload(),
            'ciphertext' => UploadedFile::fake()->createWithContent('ciphertext.bin', $ciphertext),
        ])->assertCreated();

        $attachment = ChatMessageAttachment::findOrFail($response->json('attachment.id'));
        $message = $attachment->message;
        $this->assertNull($message->body);
        $this->assertTrue($attachment->is_encrypted);
        $this->assertSame('encrypted-attachment.bin', $attachment->original_name);
        $this->assertSame('application/octet-stream', $attachment->mime_type);
        $this->assertStringStartsWith('chat/e2ee/'.$direct->id.'/', $attachment->file_path);
        $this->assertSame($ciphertext, Storage::disk('local')->get($attachment->file_path));
        $this->assertStringNotContainsString($plaintext, Storage::disk('local')->get($attachment->file_path));
        $this->assertSame(strlen($ciphertext), $attachment->ciphertext_size);
        $this->assertDatabaseMissing('chat_message_attachments', ['original_name' => 'private.pdf']);
    }

    public function test_ciphertext_upload_is_direct_participant_only_and_other_domains_are_rejected(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $agentC = $this->user('agent');
        $admin = $this->user('admin');
        $player = $this->user('player');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $payload = [...$this->attachmentPayload(), 'ciphertext' => UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(80))];

        $this->actingAs($agentC)->post(route('team.e2ee.conversations.attachments.store', $direct), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('team.e2ee.conversations.attachments.store', $direct), $payload)->assertForbidden();

        $conversations = app(ConversationService::class);
        $group = $conversations->createGroupConversation($agentA, 'Group', [$agentB, $agentC]);
        $channel = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        $support = $conversations->getOrCreatePlayerConversation($player);
        foreach ([$group, $channel, $support] as $invalid) {
            $this->actingAs($agentA)->post(route('team.e2ee.conversations.attachments.store', $invalid), $payload)->assertForbidden();
        }

        $agentD = $this->user('agent');
        [$adminDirect] = $this->activateDirect($admin, $agentD);
        $this->actingAs($admin)->post(route('team.e2ee.conversations.attachments.store', $adminDirect), [
            ...$this->attachmentPayload(),
            'ciphertext' => UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(80)),
        ])->assertCreated();
    }

    public function test_attachment_envelopes_size_and_plaintext_metadata_are_strictly_revalidated(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $base = [...$this->attachmentPayload(), 'ciphertext' => UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(80))];

        $this->actingAs($agentA)->withHeader('Accept', 'application/json')->post(route('team.e2ee.conversations.attachments.store', $direct), [
            ...$base, 'original_name' => 'private.pdf',
        ])->assertUnprocessable()->assertJsonValidationErrors('original_name');
        $this->actingAs($agentA)->withHeader('Accept', 'application/json')->post(route('team.e2ee.conversations.attachments.store', $direct), [
            ...$base, 'encrypted_metadata' => '{broken',
        ])->assertServerError();
        $this->assertThrows(fn () => app(ChatAttachmentService::class)->sendEncryptedUpload(
            $direct,
            $agentA,
            UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(80)),
            [...$this->attachmentPayload(), 'key_version' => 2],
        ), DomainException::class);
        $this->actingAs($agentA)->withHeader('Accept', 'application/json')->post(route('team.e2ee.conversations.attachments.store', $direct), [
            ...$this->attachmentPayload(),
            'ciphertext' => UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(ChatAttachmentService::E2EE_CIPHERTEXT_MAX_BYTES + 1)),
        ])->assertUnprocessable()->assertJsonValidationErrors('ciphertext');
        $this->assertSame(0, ChatMessageAttachment::count());
    }

    public function test_protected_endpoint_returns_ciphertext_and_delete_removes_record_and_private_file(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $outsider = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $bytes = random_bytes(96);
        $attachment = app(ChatAttachmentService::class)->sendEncryptedUpload(
            $direct,
            $agentA,
            UploadedFile::fake()->createWithContent('ciphertext.bin', $bytes),
            $this->attachmentPayload(),
        );
        $path = $attachment->file_path;

        $response = $this->actingAs($agentB)->get(route('team.attachments.view', $attachment))->assertOk();
        $this->assertSame('application/octet-stream', $response->headers->get('content-type'));
        $this->assertSame($bytes, $response->baseResponse->getFile()->getContent());
        $this->actingAs($outsider)->get(route('team.attachments.view', $attachment))->assertForbidden();

        app(ChatMessageService::class)->deleteInternalMessage($attachment->message, $agentA);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('chat_message_attachments', ['id' => $attachment->id]);
        $this->actingAs($agentB)->get(route('team.attachments.view', $attachment->id))->assertNotFound();
    }

    public function test_failed_message_association_cleans_the_encrypted_orphan_file(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $agentC = $this->user('agent');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $other = app(ConversationService::class)->getOrCreateDirectConversation($agentA, $agentC);
        $otherMessage = app(ChatMessageService::class)->sendInternalMessage($other, $agentA, 'Other conversation');

        try {
            app(ChatAttachmentService::class)->sendEncryptedUpload(
                $direct,
                $agentA,
                UploadedFile::fake()->createWithContent('ciphertext.bin', random_bytes(80)),
                [...$this->attachmentPayload(), 'reply_to_message_id' => $otherMessage->id],
            );
            $this->fail('Cross-conversation reply should fail.');
        } catch (\Throwable) {
            $this->assertSame([], Storage::disk('local')->allFiles('chat/e2ee'));
            $this->assertSame(0, ChatMessageAttachment::count());
        }
    }

    public function test_encrypted_reactions_are_opaque_replaceable_removable_and_participant_scoped(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $outsider = $this->user('agent');
        $admin = $this->user('admin');
        [$direct] = $this->activateDirect($agentA, $agentB);
        $message = app(E2eeMessageService::class)->send($direct, $agentA, fake()->uuid(), $this->envelope(), 1, 1);
        $encryptedHeart = $this->envelope();

        $this->actingAs($agentB)->putJson(route('team.e2ee.messages.reactions.store', $message), [
            'encrypted_reaction' => $encryptedHeart,
            'encryption_version' => 1,
            'key_version' => 1,
            'reaction' => '❤️',
        ])->assertUnprocessable()->assertJsonValidationErrors('reaction');
        $this->actingAs($agentB)->putJson(route('team.e2ee.messages.reactions.store', $message), [
            'encrypted_reaction' => $encryptedHeart,
            'encryption_version' => 1,
            'key_version' => 1,
        ])->assertOk();
        $stored = ChatMessageReaction::where('message_id', $message->id)->where('user_id', $agentB->id)->firstOrFail();
        $this->assertSame('', $stored->reaction);
        $this->assertSame($encryptedHeart, $stored->encrypted_reaction);
        $this->assertStringNotContainsString('❤️', $stored->encrypted_reaction);
        $replacement = $this->envelope();
        $updated = app(E2eeReactionService::class)->store($message, $agentB, $replacement, 1, 1);
        $this->assertSame($stored->id, $updated->id);
        $this->assertSame($replacement, $updated->encrypted_reaction);
        $this->assertThrows(fn () => app(E2eeReactionService::class)->store($message, $outsider, $this->envelope(), 1, 1), AuthorizationException::class);
        $this->assertThrows(fn () => app(E2eeReactionService::class)->store($message, $admin, $this->envelope(), 1, 1), AuthorizationException::class);
        $this->assertThrows(fn () => app(E2eeReactionService::class)->store($message, $agentB, '{bad', 1, 1), DomainException::class);
        $this->assertThrows(fn () => app(E2eeReactionService::class)->store($message, $agentB, $this->envelope(2), 1, 2), DomainException::class);

        $this->actingAs($agentB)->deleteJson(route('team.e2ee.messages.reactions.destroy', $message))->assertNoContent();
        $this->assertDatabaseMissing('chat_message_reactions', ['message_id' => $message->id, 'user_id' => $agentB->id]);
        app(ChatMessageService::class)->deleteInternalMessage($message, $agentA);
        $this->assertThrows(fn () => app(E2eeReactionService::class)->store($message, $agentB, $this->envelope(), 1, 1), AuthorizationException::class);
    }

    public function test_plaintext_direct_group_noticeboard_and_messenger_behavior_remain_separate(): void
    {
        Storage::fake('local');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $agentC = $this->user('agent');
        $this->user('admin');
        $conversations = app(ConversationService::class);
        $plain = $conversations->getOrCreateDirectConversation($agentA, $agentB);
        $plainMessage = app(ChatMessageService::class)->sendInternalMessage($plain, $agentA, 'Plain direct');
        $this->assertSame('👍', app(ChatMessageService::class)->addReaction($plainMessage, $agentB, '👍')->reaction);
        $plainAttachment = app(ChatAttachmentService::class)->sendWithUpload(
            $plain,
            $agentA,
            UploadedFile::fake()->createWithContent('safe.txt', 'plain legacy bytes'),
        );
        $this->assertFalse($plainAttachment->fresh()->is_encrypted);

        $group = $conversations->createGroupConversation($agentA, 'Group', [$agentB, $agentC]);
        $groupMessage = app(ChatMessageService::class)->sendInternalMessage($group, $agentA, 'Group');
        $this->assertSame('🙏', app(ChatMessageService::class)->addReaction($groupMessage, $agentB, '🙏')->reaction);
        $noticeboard = app(BrahmaNoticeboardService::class)->ensureAndSyncParticipants();
        $notice = app(ChatMessageService::class)->sendInternalMessage($noticeboard, User::role('admin')->firstOrFail(), 'Notice');
        $this->assertThrows(fn () => app(ChatMessageService::class)->addReaction($notice, $agentA, '👍'), AuthorizationException::class);

        [$encrypted] = $this->activateDirect($agentA, $agentC);
        $encryptedMessage = app(E2eeMessageService::class)->send($encrypted, $agentA, fake()->uuid(), $this->envelope(), 1, 1);
        $this->assertSame('Encrypted message', app(MessengerOverviewService::class)->recent($agentA)->firstWhere('id', $encrypted->id)['preview']);
        $this->assertNull($encryptedMessage->body);
    }

    private function activateDirect(User $a, User $b): array
    {
        $direct = app(ConversationService::class)->getOrCreateDirectConversation($a, $b);
        $devices = collect([
            app(E2eeDeviceService::class)->register($a, $this->devicePayload('A')),
            app(E2eeDeviceService::class)->register($b, $this->devicePayload('B')),
        ]);
        $wrapped = $devices->map(fn ($device) => [
            'device_id' => $device->id,
            'wrapped_key' => $this->encoded(80),
            'wrapping_algorithm' => 'x25519-sealedbox',
            'format_version' => 1,
        ])->all();

        return [app(E2eeActivationService::class)->activate($direct, $a, 1, $wrapped), $devices];
    }

    private function attachmentPayload(): array
    {
        return [
            'client_message_uuid' => fake()->uuid(),
            'client_attachment_uuid' => fake()->uuid(),
            'encrypted_payload' => $this->envelope(),
            'encrypted_key' => $this->envelope(),
            'encrypted_metadata' => $this->envelope(),
            'encryption_version' => 1,
            'key_version' => 1,
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

    private function encoded(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
