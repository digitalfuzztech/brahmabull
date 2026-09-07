<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalChannelService
{
    public const MODES = ['open', 'read_only', 'restricted'];

    public function __construct(private readonly ChatAuthorizationService $authorization) {}

    public function create(User $admin, array $attributes, array $publisherIds = []): ChatConversation
    {
        $this->authorization->assertCanManageChatSettings($admin);
        $this->assertMode($attributes['channel_mode'] ?? 'read_only');

        return DB::transaction(function () use ($admin, $attributes, $publisherIds): ChatConversation {
            $base = Str::slug(ltrim((string) $attributes['name'], '#')) ?: 'channel';
            $key = $base;
            for ($suffix = 2; ChatConversation::where('channel_key', $key)->exists(); $suffix++) {
                $key = $base.'-'.$suffix;
            }
            if ($key === BrahmaNoticeboardService::CHANNEL_KEY) {
                $key .= '-2';
            }

            $channel = ChatConversation::create([
                'conversation_type' => 'internal_channel',
                'channel_key' => $key,
                'name' => '#'.ltrim(trim((string) $attributes['name']), '#'),
                'channel_description' => $attributes['channel_description'] ?? null,
                'channel_mode' => $attributes['channel_mode'],
                'created_by' => $admin->id,
            ]);
            $this->syncParticipants($channel, $publisherIds);

            return $channel->fresh('participants.user.roles');
        });
    }

    public function update(User $admin, ChatConversation $channel, array $attributes, array $publisherIds = []): ChatConversation
    {
        $this->assertManageable($admin, $channel);
        $this->assertMode($attributes['channel_mode'] ?? $channel->channel_mode);

        return DB::transaction(function () use ($channel, $attributes, $publisherIds): ChatConversation {
            $locked = ChatConversation::whereKey($channel->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'name' => '#'.ltrim(trim((string) $attributes['name']), '#'),
                'channel_description' => $attributes['channel_description'] ?? null,
                'channel_mode' => $attributes['channel_mode'],
            ]);
            $this->syncParticipants($locked, $publisherIds);

            return $locked->fresh('participants.user.roles');
        });
    }

    public function setArchived(User $admin, ChatConversation $channel, bool $archived): ChatConversation
    {
        $this->assertManageable($admin, $channel);
        $channel->update(['is_archived' => $archived]);

        return $channel->fresh();
    }

    public function block(User $admin, ChatConversation $channel, User $member): void
    {
        $this->assertManageable($admin, $channel);
        if (! $member->hasRole('agent')) {
            throw new AuthorizationException('Only Agents may be blocked from a channel.');
        }
        $channel->participants()->where('user_id', $member->id)->update([
            'channel_blocked_at' => now(), 'channel_can_post' => false, 'left_at' => now(),
        ]);
    }

    public function unblock(User $admin, ChatConversation $channel, User $member): void
    {
        $this->assertManageable($admin, $channel);
        $participant = $channel->participants()->where('user_id', $member->id)->firstOrFail();
        $participant->update(['channel_blocked_at' => null, 'left_at' => null, 'joined_at' => now()]);
    }

    public function syncParticipants(ChatConversation $channel, array $publisherIds = []): void
    {
        $publisherIds = collect($publisherIds)->map(fn ($id) => (int) $id)->unique();
        $staff = User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
            ->with('roles:id,name')->get(['id']);
        $eligibleIds = $staff->pluck('id');
        ChatConversationParticipant::query()->where('conversation_id', $channel->id)
            ->whereNull('channel_blocked_at')->whereNull('left_at')->whereNotIn('user_id', $eligibleIds)
            ->update(['left_at' => now(), 'channel_can_post' => false]);

        foreach ($staff as $user) {
            $participant = ChatConversationParticipant::firstOrNew([
                'conversation_id' => $channel->id, 'user_id' => $user->id,
            ]);
            if ($participant->exists && $participant->channel_blocked_at !== null) {
                continue;
            }
            $participant->fill([
                'participant_role' => $user->hasRole('admin') ? 'owner' : 'member',
                'channel_can_post' => $user->hasRole('agent') && $publisherIds->contains($user->id),
                'joined_at' => $participant->joined_at ?? now(), 'left_at' => null,
            ])->save();
        }
    }

    public function syncAllStaffWideChannels(): void
    {
        ChatConversation::query()->where('conversation_type', 'internal_channel')
            ->where('channel_key', '!=', BrahmaNoticeboardService::CHANNEL_KEY)->get()
            ->each(fn (ChatConversation $channel) => $this->syncParticipants(
                $channel,
                $channel->participants()->where('channel_can_post', true)->pluck('user_id')->all(),
            ));
    }

    private function assertManageable(User $admin, ChatConversation $channel): void
    {
        $this->authorization->assertCanManageChatSettings($admin);
        if ($channel->conversation_type !== 'internal_channel' || $channel->channel_key === BrahmaNoticeboardService::CHANNEL_KEY) {
            throw new AuthorizationException('The protected noticeboard cannot be changed here.');
        }
    }

    private function assertMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new AuthorizationException('Invalid channel mode.');
        }
    }
}
