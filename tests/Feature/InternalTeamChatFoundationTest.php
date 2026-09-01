<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ConversationService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

class InternalTeamChatFoundationTest extends TestCase
{
    use RefreshDatabase;

    private ConversationService $conversations;

    private ChatMessageService $messages;

    private ChatAuthorizationService $authorization;

    private ChatAttachmentService $attachments;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }

        $this->conversations = app(ConversationService::class);
        $this->messages = app(ChatMessageService::class);
        $this->authorization = app(ChatAuthorizationService::class);
        $this->attachments = app(ChatAttachmentService::class);
    }

    public function test_conversation_types_enforce_support_and_internal_player_semantics(): void
    {
        $player = $this->userWithRole('player');
        $support = $this->conversations->getOrCreatePlayerConversation($player);

        $this->assertSame('support', $support->conversation_type);
        $this->assertSame($player->id, $support->player_id);
        $this->assertSame('bot', $support->status);
        $this->assertNull($support->name);

        $this->assertException(InvalidArgumentException::class, fn () => ChatConversation::create([
            'conversation_type' => 'support',
            'status' => 'bot',
        ]));

        $first = $this->userWithRole('agent');
        $second = $this->userWithRole('agent');
        $third = $this->userWithRole('agent');
        $direct = $this->conversations->getOrCreateDirectConversation($first, $second);
        $group = $this->conversations->createGroupConversation($first, 'Operations', [$second, $third]);

        $this->assertSame('internal_direct', $direct->conversation_type);
        $this->assertNull($direct->player_id);
        $this->assertNull($direct->status);
        $this->assertSame('internal_group', $group->conversation_type);
        $this->assertNull($group->player_id);
        $this->assertNull($group->status);
    }

    public function test_direct_threads_are_deterministic_unique_and_have_exactly_two_staff_participants(): void
    {
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $admin = $this->userWithRole('admin');

        $first = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);
        $same = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);
        $reverse = $this->conversations->getOrCreateDirectConversation($agentTwo, $agentOne);
        $expectedKey = collect([$agentOne->id, $agentTwo->id])->sort()->implode(':');

        $this->assertTrue($first->is($same));
        $this->assertTrue($first->is($reverse));
        $this->assertSame($expectedKey, $first->direct_key);
        $this->assertSame(2, $first->activeParticipants()->count());
        $this->assertEqualsCanonicalizing(
            [$agentOne->id, $agentTwo->id],
            $first->activeParticipants()->pluck('user_id')->all(),
        );

        $adminDirect = $this->conversations->getOrCreateDirectConversation($admin, $agentOne);
        $this->assertSame(2, $adminDirect->activeParticipants()->count());
        $this->assertTrue($adminDirect->activeParticipants()->where('user_id', $admin->id)->exists());

        $this->assertException(QueryException::class, fn () => ChatConversation::create([
            'conversation_type' => 'internal_direct',
            'created_by' => $agentOne->id,
            'direct_key' => $expectedKey,
        ]));

        $this->assertException(AuthorizationException::class, fn () => $this->conversations
            ->getOrCreateDirectConversation($agentOne, $this->userWithRole('player')));
    }

    public function test_direct_privacy_has_no_admin_override_and_only_participants_can_send(): void
    {
        $agentOne = $this->userWithRole('agent');
        $agentTwo = $this->userWithRole('agent');
        $agentThree = $this->userWithRole('agent');
        $admin = $this->userWithRole('admin');
        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);

        $this->assertTrue($this->conversations->viewConversation($direct, $agentOne)->is($direct));
        $this->assertTrue($this->conversations->viewConversation($direct, $agentTwo)->is($direct));
        $this->assertException(AuthorizationException::class, fn () => $this->conversations->viewConversation($direct, $admin));
        $this->assertException(AuthorizationException::class, fn () => $this->conversations->viewConversation($direct, $agentThree));
        $this->assertException(AuthorizationException::class, fn () => $this->messages->sendInternalMessage($direct, $admin, 'Oversight attempt'));
        $this->assertException(AuthorizationException::class, fn () => $this->messages->sendInternalMessage($direct, $agentThree, 'Unrelated attempt'));

        $message = $this->messages->sendInternalMessage($direct, $agentOne, 'Private team message');
        $internal = $message->toInternalArray();

        $this->assertSame('agent', $message->sender_type);
        $this->assertSame($agentOne->id, $message->sender_id);
        $this->assertSame($agentOne->name, $internal['sender']['name']);
        $this->assertSame('agent', $internal['sender']['role']);
        $this->assertException(LogicException::class, fn () => $message->toPlayerSafeArray());
    }

    public function test_group_creation_assigns_owner_and_members_and_blocks_invalid_or_duplicate_participants(): void
    {
        [$owner, $memberOne, $memberTwo, $memberThree] = $this->agents(4);
        $group = $this->conversations->createGroupConversation($owner, 'Support Operations', [$memberOne, $memberTwo]);

        $this->assertSame('Support Operations', $group->name);
        $this->assertSame($owner->id, $group->created_by);
        $this->assertSame(3, $group->activeParticipants()->count());
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $group->id,
            'user_id' => $owner->id,
            'participant_role' => 'owner',
            'left_at' => null,
        ]);
        $this->assertSame(2, $group->activeParticipants()->where('participant_role', 'member')->count());

        $this->assertException(DomainException::class, fn () => $this->conversations
            ->addGroupParticipant($group, $owner, $memberOne));
        $this->assertException(AuthorizationException::class, fn () => $this->conversations
            ->addGroupParticipant($group, $memberOne, $memberThree));
        $this->assertException(AuthorizationException::class, fn () => $this->conversations
            ->addGroupParticipant($group, $owner, $this->userWithRole('player')));
        $this->assertException(DomainException::class, fn () => $this->conversations
            ->createGroupConversation($owner, 'Too Small', [$memberOne]));

        $added = $this->conversations->addGroupParticipant($group, $owner, $memberThree);
        $this->assertSame('member', $added->participant_role);
        $this->assertSame(4, $group->activeParticipants()->count());
    }

    public function test_group_members_send_owner_manages_and_member_can_leave(): void
    {
        [$owner, $memberOne, $memberTwo] = $this->agents(3);
        $group = $this->conversations->createGroupConversation($owner, 'Original Name', [$memberOne, $memberTwo]);

        $message = $this->messages->sendInternalMessage($group, $memberOne, 'Member message');
        $this->assertSame($memberOne->id, $message->sender_id);

        $renamed = $this->conversations->renameGroup($group, $owner, 'Renamed Group');
        $this->assertSame('Renamed Group', $renamed->name);
        $this->assertException(AuthorizationException::class, fn () => $this->conversations
            ->renameGroup($group, $memberOne, 'Forbidden Rename'));

        $this->conversations->leaveGroup($group, $memberTwo);
        $this->assertNotNull($group->participants()->where('user_id', $memberTwo->id)->firstOrFail()->left_at);
        $this->assertSame(2, $group->activeParticipants()->count());
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->sendInternalMessage($group, $memberTwo, 'After leaving'));
    }

    public function test_owner_leave_transfers_ownership_instead_of_leaving_group_unmanaged(): void
    {
        [$owner, $memberOne, $memberTwo] = $this->agents(3);
        $group = $this->conversations->createGroupConversation($owner, 'Ownership', [$memberOne, $memberTwo]);

        $this->conversations->leaveGroup($group, $owner);

        $this->assertNotNull($group->participants()->where('user_id', $owner->id)->firstOrFail()->left_at);
        $this->assertSame(1, $group->activeParticipants()->where('participant_role', 'owner')->count());
        $this->assertFalse($group->fresh()->is_archived);
    }

    public function test_admin_group_oversight_is_dynamic_read_only_and_never_creates_participation(): void
    {
        [$owner, $memberOne, $memberTwo] = $this->agents(3);
        $admin = $this->userWithRole('admin');
        $group = $this->conversations->createGroupConversation($owner, 'Observed Group', [$memberOne, $memberTwo]);
        $message = $this->messages->sendInternalMessage($group, $owner, 'Visible to oversight');

        $this->assertTrue($this->authorization->canReadInternalGroupAsAdmin($group, $admin));
        $this->assertTrue($this->conversations->viewConversation($group, $admin)->is($group));
        $this->assertFalse($group->participants()->where('user_id', $admin->id)->exists());
        $this->assertSame(3, $group->activeParticipants()->count());
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->sendInternalMessage($group, $admin, 'Admin must be read-only'));
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->replyToInternalMessage($group, $message, $admin, 'No admin reply'));
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->addReaction($message, $admin, "\u{1F44D}"));
        $this->assertException(AuthorizationException::class, fn () => $this->attachments
            ->createMetadata($message, $admin, $this->imageMetadata()));

        $this->conversations->removeGroupParticipant($group, $owner, $memberTwo);

        $this->assertSame(2, $group->activeParticipants()->count());
        $this->assertFalse($this->authorization->canReadInternalGroupAsAdmin($group->fresh(), $admin));
        $this->assertException(AuthorizationException::class, fn () => $this->conversations
            ->viewConversation($group->fresh(), $admin));
    }

    public function test_non_member_agent_cannot_read_group(): void
    {
        [$owner, $memberOne, $memberTwo, $outsider] = $this->agents(4);
        $group = $this->conversations->createGroupConversation($owner, 'Private Group', [$memberOne, $memberTwo]);

        $this->assertException(AuthorizationException::class, fn () => $this->conversations
            ->viewConversation($group, $outsider));
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->sendInternalMessage($group, $outsider, 'No access'));
    }

    public function test_internal_replies_are_same_conversation_and_authorized(): void
    {
        [$agentOne, $agentTwo, $outsider] = $this->agents(3);
        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);
        $other = $this->conversations->getOrCreateDirectConversation($agentOne, $outsider);
        $original = $this->messages->sendInternalMessage($direct, $agentOne, 'Original');
        $reply = $this->messages->replyToInternalMessage($direct, $original, $agentTwo, 'Reply');

        $this->assertSame($original->id, $reply->reply_to_message_id);
        $this->assertTrue($reply->replyTo->is($original));
        $this->assertTrue($original->replies->contains($reply));
        $this->assertException(DomainException::class, fn () => $this->messages
            ->replyToInternalMessage($other, $original, $outsider, 'Cross conversation'));
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->replyToInternalMessage($direct, $original, $outsider, 'Unauthorized'));
    }

    public function test_internal_reactions_are_allowlisted_unique_updatable_and_removable(): void
    {
        [$agentOne, $agentTwo] = $this->agents(2);
        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);
        $message = $this->messages->sendInternalMessage($direct, $agentOne, 'React to this');

        $reaction = $this->messages->addReaction($message, $agentTwo, "\u{1F44D}");
        $this->assertSame("\u{1F44D}", $reaction->reaction);
        $changed = $this->messages->addReaction($message, $agentTwo, "\u{2764}\u{FE0F}");
        $this->assertSame($reaction->id, $changed->id);
        $this->assertSame("\u{2764}\u{FE0F}", $changed->fresh()->reaction);
        $this->assertSame(1, $message->reactions()->where('user_id', $agentTwo->id)->count());

        $this->messages->removeReaction($message, $agentTwo);
        $this->assertSame(0, $message->reactions()->count());
        $this->assertException(DomainException::class, fn () => $this->messages
            ->addReaction($message, $agentTwo, "\u{1F525}"));

        $player = $this->userWithRole('player');
        $support = $this->conversations->getOrCreatePlayerConversation($player);
        $supportMessage = $this->messages->sendPlayerMessage($support, $player, 'Support message');
        $this->assertException(AuthorizationException::class, fn () => $this->messages
            ->addReaction($supportMessage, $player, "\u{1F44D}"));
    }

    public function test_attachment_metadata_is_internal_authorized_validated_and_relationship_backed(): void
    {
        [$agentOne, $agentTwo, $outsider] = $this->agents(3);
        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);
        $message = $this->messages->sendInternalMessage($direct, $agentOne, 'Attachment metadata');
        $attachment = $this->attachments->createMetadata($message, $agentTwo, $this->imageMetadata());

        $this->assertSame('image', $attachment->media_type);
        $this->assertSame('internal/team/example.png', $attachment->file_path);
        $this->assertTrue($attachment->message->is($message));
        $this->assertTrue($message->attachments->contains($attachment));
        $this->assertException(AuthorizationException::class, fn () => $this->attachments
            ->createMetadata($message, $outsider, $this->imageMetadata()));
        $this->assertException(ValidationException::class, fn () => $this->attachments
            ->createMetadata($message, $agentOne, array_merge($this->imageMetadata(), ['media_type' => 'binary'])));
        $this->assertException(ValidationException::class, fn () => $this->attachments
            ->createMetadata($message, $agentOne, array_merge($this->imageMetadata(), ['mime_type' => 'video/mp4'])));

        $player = $this->userWithRole('player');
        $support = $this->conversations->getOrCreatePlayerConversation($player);
        $supportMessage = $this->messages->sendPlayerMessage($support, $player, 'No support attachment');
        $this->assertException(AuthorizationException::class, fn () => $this->attachments
            ->createMetadata($supportMessage, $player, $this->imageMetadata()));
    }

    public function test_direct_unread_state_is_independent_and_own_messages_are_excluded(): void
    {
        [$agentOne, $agentTwo] = $this->agents(2);
        $direct = $this->conversations->getOrCreateDirectConversation($agentOne, $agentTwo);

        $this->messages->sendInternalMessage($direct, $agentOne, 'First');
        $this->assertSame(0, $this->conversations->internalUnreadCount($direct, $agentOne));
        $this->assertSame(1, $this->conversations->internalUnreadCount($direct, $agentTwo));
        $this->assertSame(1, $this->conversations->internalDirectUnreadCount($agentTwo));

        $this->conversations->markInternalConversationRead($direct, $agentTwo);
        $this->assertNotNull($direct->participants()->where('user_id', $agentTwo->id)->firstOrFail()->last_read_at);
        $this->assertSame(0, $this->conversations->internalUnreadCount($direct, $agentTwo));
        $this->assertException(DomainException::class, fn () => $this->messages
            ->markMessagesReadByStaff($direct, $agentTwo));

        $this->travel(1)->seconds();
        $this->messages->sendInternalMessage($direct, $agentTwo, 'Second');
        $this->assertSame(1, $this->conversations->internalUnreadCount($direct, $agentOne));
        $this->assertSame(0, $this->conversations->internalUnreadCount($direct, $agentTwo));
    }

    public function test_group_unread_state_is_independent_per_participant(): void
    {
        [$owner, $memberOne, $memberTwo, $laterMember] = $this->agents(4);
        $group = $this->conversations->createGroupConversation($owner, 'Unread Group', [$memberOne, $memberTwo]);

        $this->messages->sendInternalMessage($group, $owner, 'One');
        $this->assertSame(1, $this->conversations->internalUnreadCount($group, $memberOne));
        $this->assertSame(1, $this->conversations->internalUnreadCount($group, $memberTwo));

        $this->conversations->markInternalConversationRead($group, $memberOne);
        $this->travel(1)->seconds();
        $this->messages->sendInternalMessage($group, $memberOne, 'Two');

        $this->assertSame(0, $this->conversations->internalUnreadCount($group, $memberOne));
        $this->assertSame(2, $this->conversations->internalUnreadCount($group, $memberTwo));
        $this->assertSame(2, $this->conversations->internalGroupUnreadCount($memberTwo));
        $this->assertSame(1, $this->conversations->internalUnreadCount($group, $owner));

        $this->travel(1)->seconds();
        $this->conversations->addGroupParticipant($group, $owner, $laterMember);
        $this->assertSame(0, $this->conversations->internalUnreadCount($group, $laterMember));
    }

    private function imageMetadata(): array
    {
        return [
            'media_type' => 'image',
            'file_path' => 'internal/team/example.png',
            'original_name' => 'example.png',
            'mime_type' => 'image/png',
            'file_size' => 2048,
            'width' => 640,
            'height' => 480,
        ];
    }

    private function agents(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn () => $this->userWithRole('agent'))
            ->all();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.fake()->unique()->numberBetween(1000, 999999),
            'role' => $role,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function assertException(string $expected, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected exception [{$expected}] was not thrown.");
        } catch (Throwable $exception) {
            $this->assertInstanceOf($expected, $exception);
        }
    }
}
