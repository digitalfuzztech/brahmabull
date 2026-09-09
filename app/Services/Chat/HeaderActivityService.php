<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HeaderActivityService
{
    public function initialCursors(User $viewer): array
    {
        return [
            'message' => (int) ChatMessage::query()->max('id'),
            'notification' => (int) Notification::query()->where('user_id', $viewer->id)->max('id'),
        ];
    }

    public function after(User $viewer, int $messageCursor, int $notificationCursor): array
    {
        $latestMessageId = (int) ChatMessage::query()->max('id');
        $latestNotificationId = (int) Notification::query()->where('user_id', $viewer->id)->max('id');

        $team = $viewer->hasAnyRole(['admin', 'agent']) ? ChatMessage::query()
            ->where('id', '>', $messageCursor)
            ->where('id', '<=', $latestMessageId)
            ->whereNotNull('sender_id')
            ->where('sender_id', '!=', $viewer->id)
            ->whereHas('conversation', function ($query) use ($viewer): void {
                $query->whereIn('conversation_type', ['internal_direct', 'internal_group', 'internal_channel'])
                    ->where('is_archived', false)
                    ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $viewer->id));
            })
            ->with(['conversation.activeParticipants.user:id,name', 'sender:id,name', 'attachments:id,message_id,media_type'])
            ->oldest('id')->limit(20)->get()
            ->map(fn (ChatMessage $message) => $this->teamAlert($message)) : collect();

        $support = $viewer->hasAnyRole(['admin', 'agent']) ? ChatMessage::query()
            ->where('id', '>', $messageCursor)
            ->where('id', '<=', $latestMessageId)
            ->where('sender_type', 'player')
            ->whereHas('conversation', function ($query) use ($viewer): void {
                $query->where('conversation_type', 'support');
                if ($viewer->hasRole('agent')) {
                    $query->where(fn ($query) => $query
                        ->where('status', '!=', 'resolved')
                        ->orWhere('assigned_to', $viewer->id));
                }
            })
            ->with(['conversation.player:id,name', 'sender:id,name'])
            ->oldest('id')->limit(20)->get()
            ->map(fn (ChatMessage $message) => [
                'key' => 'support-message:'.$message->id,
                'kind' => 'support',
                'title' => 'Support',
                'subtitle' => ($message->conversation?->player?->name ?? 'A player').' sent a message',
                'preview' => Str::limit((string) $message->body, 100),
                'conversation_id' => $message->conversation_id,
                'notification_id' => null,
            ]) : collect();

        $notificationRows = Notification::query()
            ->where('user_id', $viewer->id)
            ->where('id', '>', $notificationCursor)
            ->where('id', '<=', $latestNotificationId)
            ->oldest('id')->limit(20)->get();
        $readyNotifications = $notificationRows->takeWhile(function (Notification $notification): bool {
            $floatAfter = data_get($notification->data, 'float_after');

            return blank($floatAfter) || Carbon::parse($floatAfter)->isPast();
        });
        $notifications = $readyNotifications
            ->map(fn (Notification $notification) => [
                'key' => 'notification:'.$notification->id,
                'kind' => 'notification',
                'title' => $notification->title ?: 'Notification',
                'subtitle' => 'New notification',
                'preview' => Str::limit((string) $notification->message, 100),
                'conversation_id' => null,
                'notification_id' => $notification->id,
            ]);

        return [
            'alerts' => $team->concat($support)->concat($notifications)->values()->all(),
            'message_cursor' => max($messageCursor, $latestMessageId),
            'notification_cursor' => max($notificationCursor, (int) ($readyNotifications->last()?->id ?? $notificationCursor)),
        ];
    }

    private function teamAlert(ChatMessage $message): array
    {
        $conversation = $message->conversation;
        $isDirect = $conversation?->conversation_type === 'internal_direct';
        $title = $isDirect ? ($message->sender?->name ?? 'Team member') : ($conversation?->name ?? 'Team');
        $preview = $message->encrypted_payload
            ? 'Sent you an encrypted message'
            : (filled($message->body)
                ? Str::limit($message->body, 100)
                : ($message->attachments->first() ? 'Sent an attachment' : 'Sent a message'));

        return [
            'key' => 'team-message:'.$message->id,
            'kind' => 'team',
            'title' => $title,
            'subtitle' => $isDirect ? 'Sent you a message' : 'New Team message',
            'preview' => $preview,
            'conversation_id' => $message->conversation_id,
            'notification_id' => null,
        ];
    }
}
