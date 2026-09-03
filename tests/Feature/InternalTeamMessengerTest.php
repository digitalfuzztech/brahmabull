<?php

namespace Tests\Feature;

use App\Livewire\Admin\SupportInbox;
use App\Livewire\Admin\TeamMessenger;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\TeamInboxService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternalTeamMessengerTest extends TestCase
{
    use RefreshDatabase;

    private ConversationService $conversations;

    private ChatMessageService $messages;

    private ChatAttachmentService $attachments;

    private TeamInboxService $team;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }

        Storage::fake('local');
        $this->conversations = app(ConversationService::class);
        $this->messages = app(ChatMessageService::class);
        $this->attachments = app(ChatAttachmentService::class);
        $this->team = app(TeamInboxService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_support_and_team_domains_are_role_scoped_and_query_sets_are_isolated(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $player = $this->user('player');
        $support = $this->conversations->getOrCreatePlayerConversation($player);
        $direct = $this->conversations->getOrCreateDirectConversation($admin, $agent);

        Livewire::actingAs($admin)->test(SupportInbox::class)
            ->assertSee('Support')
            ->assertSee('Team')
            ->call('selectDomain', 'team')
            ->assertSet('domain', 'team')
            ->assertSeeLivewire('admin.team-messenger');
        Livewire::actingAs($agent)->test(TeamMessenger::class)->assertSee('Team Messenger');
        Livewire::actingAs($player)->test(TeamMessenger::class)->assertForbidden();

        $this->assertFalse($this->team->conversations($admin, 'direct')->contains('id', $support->id));
        $this->assertTrue($this->team->conversations($admin, 'direct')->contains('id', $direct->id));
        $this->assertDatabaseMissing('chat_conversation_participants', ['conversation_id' => $support->id]);
    }

    public function test_contact_picker_and_direct_threads_enforce_staff_roles_reuse_and_privacy(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent', ['name' => 'Agent Alpha', 'username' => 'alpha-agent']);
        $agentB = $this->user('agent', ['name' => 'Agent Beta']);
        $agentC = $this->user('agent');
        $player = $this->user('player');

        $agentContacts = $this->team->contacts($agentA);
        $adminContacts = $this->team->contacts($admin);
        $this->assertTrue($agentContacts->contains('id', $agentB->id));
        $this->assertTrue($agentContacts->contains('id', $admin->id));
        $this->assertFalse($agentContacts->contains('id', $player->id));
        $this->assertTrue($adminContacts->contains('id', $agentA->id));
        $this->assertFalse($adminContacts->contains('id', $player->id));

        $direct = $this->conversations->getOrCreateDirectConversation($agentA, $agentB);
        $this->assertTrue($direct->is($this->conversations->getOrCreateDirectConversation($agentB, $agentA)));
        $this->assertSame([$direct->id], $this->team->conversations($agentA, 'direct', 'beta')->pluck('id')->all());
        $this->assertSame([], $this->team->conversations($agentC, 'direct')->pluck('id')->all());
        $this->assertFalse($this->team->conversations($admin, 'direct')->contains('id', $direct->id));

        $this->expectException(AuthorizationException::class);
        app(ChatAuthorizationService::class)->assertCanViewInternal($direct, $admin);
    }

    public function test_admin_agent_direct_open_read_and_reply_use_participant_state_even_at_same_timestamp(): void
    {
        Carbon::setTestNow('2026-09-01 16:00:00');
        $admin = $this->user('admin');
        $agent = $this->user('agent');

        $component = Livewire::actingAs($admin)->test(TeamMessenger::class)
            ->call('startDirect', $agent->id)
            ->assertSet('details.type', 'internal_direct')
            ->assertSet('details.is_observer', false)
            ->assertSet('details.can_send', true);

        $direct = ChatConversation::where('direct_key', $admin->id.':'.$agent->id)->firstOrFail();
        $this->assertSame('internal_direct', $direct->conversation_type);
        $this->assertSame(2, $direct->activeParticipants()->count());
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $direct->id,
            'user_id' => $admin->id,
            'left_at' => null,
        ]);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $direct->id,
            'user_id' => $agent->id,
            'left_at' => null,
        ]);
        $this->assertNotNull($direct->participants()->where('user_id', $admin->id)->value('last_read_at'));
        $this->assertNull($direct->participants()->where('user_id', $agent->id)->value('last_read_at'));
        $this->conversations->markInternalConversationRead($direct, $admin);
        $this->conversations->markInternalConversationRead($direct, $admin);

        $component->set('message', 'Admin direct message')->call('sendTeamMessage');
        $this->assertSame(1, $this->conversations->internalUnreadCount($direct, $agent));

        $this->team->selectConversation($direct->id, $agent);
        $reply = $this->messages->sendInternalMessage($direct, $agent, 'Agent reply');
        $this->assertSame($agent->id, $reply->sender_id);
        $this->assertTrue($direct->is($this->conversations->getOrCreateDirectConversation($agent, $admin)));
    }

    public function test_direct_repair_is_pair_scoped_and_rejects_an_unexpected_third_participant(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $outsider = $this->user('agent');
        $direct = $this->conversations->getOrCreateDirectConversation($admin, $agent);
        $directKey = $direct->direct_key;

        $direct->participants()->where('user_id', $admin->id)->delete();
        $repaired = $this->conversations->getOrCreateDirectConversation($admin, $agent);
        $this->assertSame($direct->id, $repaired->id);
        $this->assertSame($directKey, $repaired->direct_key);
        $this->assertSame([$admin->id, $agent->id], $repaired->activeParticipants()->orderBy('user_id')->pluck('user_id')->all());

        $repaired->participants()->create([
            'user_id' => $outsider->id,
            'participant_role' => 'member',
            'joined_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->conversations->getOrCreateDirectConversation($admin, $agent);
    }

    public function test_direct_messages_show_internal_identity_and_use_independent_unread_state(): void
    {
        $agentA = $this->user('agent', ['name' => 'Sarah']);
        $agentB = $this->user('agent', ['name' => 'John']);
        $direct = $this->conversations->getOrCreateDirectConversation($agentA, $agentB);
        $message = $this->messages->sendInternalMessage($direct, $agentA, 'Please check this.');

        $this->assertSame(0, $this->conversations->internalUnreadCount($direct, $agentA));
        $this->assertSame(1, $this->conversations->internalUnreadCount($direct, $agentB));
        $beforeSenderRead = $direct->participants()->where('user_id', $agentA->id)->value('last_read_at');
        $this->team->selectConversation($direct->id, $agentB);
        $this->assertSame(0, $this->conversations->internalUnreadCount($direct, $agentB));
        $this->assertEquals($beforeSenderRead, $direct->participants()->where('user_id', $agentA->id)->value('last_read_at'));

        $timeline = $this->team->messages($direct, $agentB);
        $this->assertSame('Sarah', $timeline[0]['sender_name']);
        $this->assertSame($message->id, $timeline[0]['id']);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $agentB->id,
            'type' => 'chat_team_direct_message',
        ]);
    }

    public function test_agent_group_creation_management_and_owner_transfer_use_existing_domain_services(): void
    {
        $owner = $this->user('agent');
        $memberA = $this->user('agent');
        $memberB = $this->user('agent');
        $memberC = $this->user('agent');
        $admin = $this->user('admin');
        $player = $this->user('player');

        $group = $this->conversations->createGroupConversation($owner, 'Operations', [$memberA, $memberB]);
        $this->assertSame('owner', $group->participants()->where('user_id', $owner->id)->value('participant_role'));
        $this->assertSame(3, $group->activeParticipants()->count());
        $this->assertSame('Renamed Ops', $this->conversations->renameGroup($group, $owner, 'Renamed Ops')->name);
        $this->conversations->addGroupParticipant($group, $owner, $memberC);
        $this->conversations->removeGroupParticipant($group, $owner, $memberB);
        $this->assertSame(3, $group->activeParticipants()->count());

        try {
            $this->conversations->createGroupConversation($owner, 'Too Small', [$memberA]);
            $this->fail('A two-agent group was created.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        foreach ([$admin, $player] as $invalidMember) {
            try {
                $this->conversations->createGroupConversation($owner, 'Invalid Group', [$memberA, $invalidMember]);
                $this->fail('An invalid group member was accepted.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        try {
            $this->conversations->renameGroup($group, $memberA, 'Forbidden');
            $this->fail('A member renamed the group.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->conversations->leaveGroup($group, $owner);
        $this->assertNotNull($group->participants()->where('user_id', $owner->id)->value('left_at'));
        $this->assertTrue($group->activeParticipants()->where('participant_role', 'owner')->exists());
    }

    public function test_admin_group_oversight_is_dynamic_read_only_and_never_includes_direct_messages(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('agent');
        $memberA = $this->user('agent');
        $memberB = $this->user('agent');
        $direct = $this->conversations->getOrCreateDirectConversation($owner, $memberA);
        $group = $this->conversations->createGroupConversation($owner, 'Audited Group', [$memberA, $memberB]);
        $message = $this->messages->sendInternalMessage($group, $owner, 'Group update');

        $oversight = $this->team->conversations($admin, 'oversight');
        $this->assertTrue($oversight->contains('id', $group->id));
        $this->assertFalse($oversight->contains('id', $direct->id));
        $this->assertFalse($group->activeParticipants()->where('user_id', $admin->id)->exists());
        $this->assertTrue($this->team->selectConversation($group->id, $admin)->is($group));
        $this->assertFalse($group->activeParticipants()->where('user_id', $admin->id)->exists());
        $this->assertSame('Group update', $this->team->messages($group, $admin)[0]['body']);

        foreach (['send', 'reply', 'react', 'attach'] as $operation) {
            try {
                match ($operation) {
                    'send' => $this->messages->sendInternalMessage($group, $admin, 'No'),
                    'reply' => $this->messages->replyToInternalMessage($group, $message, $admin, 'No'),
                    'react' => $this->messages->addReaction($message, $admin, "\u{1F44D}"),
                    'attach' => $this->attachments->createMetadata($message, $admin, $this->metadata()),
                };
                $this->fail('Admin observer performed '.$operation.'.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->conversations->removeGroupParticipant($group, $owner, $memberB);
        $this->assertSame('internal_group', $group->fresh()->conversation_type);
        $this->assertNull($group->fresh()->direct_key);
        $this->assertFalse($this->team->conversations($admin, 'oversight')->contains('id', $group->id));

        $this->conversations->addGroupParticipant($group, $owner, $memberB);
        $this->assertTrue($this->team->conversations($admin, 'oversight')->contains('id', $group->id));
    }

    public function test_replies_and_reactions_are_scoped_and_rendered_with_quoted_context(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $agentC = $this->user('agent');
        $direct = $this->conversations->getOrCreateDirectConversation($agentA, $agentB);
        $other = $this->conversations->getOrCreateDirectConversation($agentB, $agentC);
        $original = $this->messages->sendInternalMessage($direct, $agentA, 'Original message');
        $reply = $this->messages->replyToInternalMessage($direct, $original, $agentB, 'Quoted response');
        $otherMessage = $this->messages->sendInternalMessage($other, $agentB, 'Other thread');

        $this->assertSame($original->id, $reply->reply_to_message_id);
        $this->assertSame('Original message', collect($this->team->messages($direct, $agentA))->firstWhere('id', $reply->id)['reply']['body']);
        $this->messages->addReaction($original, $agentB, "\u{2764}\u{FE0F}");
        $this->messages->addReaction($original, $agentB, "\u{1F44D}");
        $this->assertDatabaseHas('chat_message_reactions', ['message_id' => $original->id, 'user_id' => $agentB->id, 'reaction' => "\u{1F44D}"]);
        $this->messages->removeReaction($original, $agentB);
        $this->assertDatabaseMissing('chat_message_reactions', ['message_id' => $original->id, 'user_id' => $agentB->id]);

        try {
            $this->messages->replyToInternalMessage($direct, $otherMessage, $agentA, 'Cross thread');
            $this->fail('Cross-conversation reply succeeded.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        $this->messages->addReaction($original, $agentC, "\u{1F44D}");
    }

    public function test_private_uploads_support_allowed_media_and_randomized_metadata(): void
    {
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $direct = $this->conversations->getOrCreateDirectConversation($agentA, $agentB);
        $files = [
            UploadedFile::fake()->image('photo.jpg', 40, 40),
            $this->uploadedFile('report.pdf', "%PDF-1.4\n", 'application/pdf'),
            UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            UploadedFile::fake()->create('voice.mp3', 100, 'audio/mpeg'),
        ];

        foreach ($files as $index => $file) {
            $attachment = $this->attachments->sendWithUpload($direct, $agentA, $file, $index === 0 ? 'Text and image' : null);
            $this->assertStringStartsWith('chat/'.$direct->id.'/', $attachment->file_path);
            $this->assertStringNotContainsString($attachment->original_name, $attachment->file_path);
            $this->assertSame($file->getClientOriginalName(), $attachment->original_name);
            Storage::disk('local')->assertExists($attachment->file_path);
        }

        $this->assertSame(['image', 'document', 'video', 'audio'], $direct->messages()->with('attachments')->get()->pluck('attachments.0.media_type')->all());
        $this->assertNull($direct->messages()->latest('id')->first()->body);
        $this->assertSame(4, $direct->messages()->count());
        $this->assertStringNotContainsString(storage_path(), json_encode($this->team->messages($direct, $agentA)));
    }

    public function test_attachment_validation_cleanup_and_protected_routes_enforce_dynamic_authorization(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('agent');
        $memberA = $this->user('agent');
        $memberB = $this->user('agent');
        $outsider = $this->user('agent');
        $player = $this->user('player');
        $group = $this->conversations->createGroupConversation($owner, 'Media Group', [$memberA, $memberB]);
        $image = $this->attachments->sendWithUpload($group, $owner, UploadedFile::fake()->image('safe.png'));

        $this->actingAs($owner)->get(route('team.attachments.view', $image))->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        $this->actingAs($owner)->get(route('team.attachments.download', $image))->assertOk();
        $this->actingAs($admin)->get(route('team.attachments.view', $image))->assertOk();
        $this->actingAs($outsider)->get(route('team.attachments.view', $image))->assertForbidden();
        $this->actingAs($player)->get(route('team.attachments.view', $image))->assertForbidden();

        $this->conversations->removeGroupParticipant($group, $owner, $memberB);
        $this->actingAs($admin)->get(route('team.attachments.view', $image))->assertForbidden();

        foreach ([
            UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
            UploadedFile::fake()->create('fake.jpg', 1, 'application/pdf'),
            UploadedFile::fake()->create('large.pdf', ChatAttachmentService::MAX_KILOBYTES + 1, 'application/pdf'),
        ] as $invalid) {
            try {
                $this->attachments->sendWithUpload($group, $owner, $invalid);
                $this->fail('Unsafe upload was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $otherDirect = $this->conversations->getOrCreateDirectConversation($owner, $outsider);
        $otherMessage = $this->messages->sendInternalMessage($otherDirect, $owner, 'Other');
        $filesBefore = Storage::disk('local')->allFiles();

        try {
            $this->attachments->sendWithUpload($group, $owner, UploadedFile::fake()->image('orphan.png'), null, $otherMessage);
            $this->fail('Cross-conversation attachment reply succeeded.');
        } catch (DomainException) {
            $this->assertSame($filesBefore, Storage::disk('local')->allFiles());
        }
    }

    public function test_direct_attachment_privacy_and_admin_agent_direct_access_follow_participation(): void
    {
        $admin = $this->user('admin');
        $agentA = $this->user('agent');
        $agentB = $this->user('agent');
        $privateDirect = $this->conversations->getOrCreateDirectConversation($agentA, $agentB);
        $privateFile = $this->attachments->sendWithUpload($privateDirect, $agentA, UploadedFile::fake()->image('private.jpg'));

        $this->actingAs($agentB)->get(route('team.attachments.view', $privateFile))->assertOk();
        $this->actingAs($admin)->get(route('team.attachments.view', $privateFile))->assertForbidden();

        $adminDirect = $this->conversations->getOrCreateDirectConversation($admin, $agentA);
        $adminFile = $this->attachments->sendWithUpload($adminDirect, $admin, UploadedFile::fake()->image('shared.jpg'));
        $this->actingAs($admin)->get(route('team.attachments.view', $adminFile))->assertOk();
        $this->actingAs($agentA)->get(route('team.attachments.view', $adminFile))->assertOk();
        $this->actingAs($agentB)->get(route('team.attachments.view', $adminFile))->assertForbidden();
    }

    public function test_team_ui_has_exclusive_visible_polling_replies_reactions_media_and_read_only_oversight(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('agent');
        $memberA = $this->user('agent');
        $memberB = $this->user('agent');
        $group = $this->conversations->createGroupConversation($owner, 'UI Group', [$memberA, $memberB]);
        $this->messages->sendInternalMessage($group, $owner, 'Visible message');

        Livewire::actingAs($owner)->test(TeamMessenger::class)
            ->set('section', 'groups')
            ->call('selectTeamConversation', $group->id)
            ->assertSee('Visible message')
            ->assertSee('Reply')
            ->assertSee('Attach file')
            ->assertSee('wire:poll.3s.visible', false);

        Livewire::actingAs($admin)->test(TeamMessenger::class)
            ->set('section', 'oversight')
            ->call('selectTeamConversation', $group->id)
            ->assertSee('Read-only group oversight')
            ->assertDontSee('Attach file')
            ->assertDontSee('Reply');

        $view = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $supportView = file_get_contents(resource_path('views/livewire/admin/support-inbox.blade.php'));
        $this->assertStringContainsString('wire:poll.2s="pollList"', $view);
        $this->assertStringContainsString('wire:poll.3s.visible="pollSelected"', $view);
        $this->assertStringContainsString("@if(\$domain === 'team')", $supportView);
        $this->assertSame(1, substr_count($supportView, '<livewire:admin.team-messenger'));
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'username' => $role.fake()->unique()->numberBetween(1000, 999999),
            'role' => $role,
            'is_active' => true,
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function metadata(): array
    {
        return [
            'media_type' => 'document',
            'file_path' => 'chat/test/file.pdf',
            'original_name' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ];
    }

    private function uploadedFile(string $name, string $contents, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'team-chat-test-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
