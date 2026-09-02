<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BrahmaNoticeboardService
{
    public const CHANNEL_KEY = 'brahma-noticeboard';

    public const CHANNEL_NAME = '#brahma-noticeboard';

    public function ensureChannelExists(): ChatConversation
    {
        try {
            return DB::transaction(function (): ChatConversation {
                $existing = ChatConversation::query()
                    ->where('channel_key', self::CHANNEL_KEY)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $admin = User::role('admin')->where('is_active', true)->orderBy('id')->first();

                if (! $admin) {
                    throw new AuthorizationException('The noticeboard requires an active administrator.');
                }

                return ChatConversation::create([
                    'conversation_type' => 'internal_channel',
                    'channel_key' => self::CHANNEL_KEY,
                    'name' => self::CHANNEL_NAME,
                    'created_by' => $admin->id,
                ]);
            });
        } catch (QueryException $exception) {
            $conversation = ChatConversation::where('channel_key', self::CHANNEL_KEY)->first();

            if (! $conversation) {
                throw $exception;
            }

            return $conversation;
        }
    }

    public function ensureAndSyncParticipants(): ChatConversation
    {
        $channel = $this->ensureChannelExists();

        return DB::transaction(function () use ($channel): ChatConversation {
            $locked = ChatConversation::whereKey($channel->id)->lockForUpdate()->firstOrFail();
            $eligible = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
                ->with('roles:id,name')
                ->get(['id']);
            $eligibleIds = $eligible->pluck('id')->map(fn ($id) => (int) $id);

            ChatConversationParticipant::query()
                ->where('conversation_id', $locked->id)
                ->whereNull('left_at')
                ->whereNotIn('user_id', $eligibleIds)
                ->update(['left_at' => now()]);

            foreach ($eligible as $staff) {
                $participant = ChatConversationParticipant::query()
                    ->where('conversation_id', $locked->id)
                    ->where('user_id', $staff->id)
                    ->lockForUpdate()
                    ->first();
                $values = [
                    'participant_role' => $staff->hasRole('admin') ? 'owner' : 'member',
                    'joined_at' => $participant?->joined_at ?? now(),
                    'left_at' => null,
                ];

                if ($participant) {
                    $participant->update($values);
                } else {
                    ChatConversationParticipant::create($values + [
                        'conversation_id' => $locked->id,
                        'user_id' => $staff->id,
                    ]);
                }
            }

            return $locked->fresh(['activeParticipants.user.roles']);
        });
    }
}
