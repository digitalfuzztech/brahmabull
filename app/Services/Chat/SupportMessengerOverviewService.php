<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class SupportMessengerOverviewService
{
    public function unreadCount(User $staff): int
    {
        $this->assertStaff($staff);

        return $this->authorizedSupportMessages($staff)
            ->where('sender_type', 'player')
            ->whereNull('read_by_staff_at')
            ->count();
    }

    public function recent(User $staff, int $limit = 15): Collection
    {
        $this->assertStaff($staff);
        $query = ChatConversation::query()
            ->where('conversation_type', 'support')
            ->with(['player:id,name,username', 'latestMessage.attachments:id,message_id,media_type'])
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->where('sender_type', 'player')->whereNull('read_by_staff_at')]);

        $this->applyAuthorization($query, $staff);

        return $query->latest('last_message_at')->limit($limit)->get()
            ->map(function (ChatConversation $conversation): array {
                $latest = $conversation->latestMessage;

                return [
                    'id' => $conversation->id,
                    'name' => $conversation->player?->name ?? 'Player Support',
                    'preview' => $latest?->deleted_at
                        ? 'Message deleted'
                        : (filled($latest?->body)
                            ? $latest->body
                            : ($latest?->attachments->first() ? ucfirst($latest->attachments->first()->media_type) : 'No messages yet')),
                    'unread_count' => (int) $conversation->unread_count,
                    'relative_time' => ($conversation->last_message_at ?? $conversation->created_at)?->diffForHumans(),
                ];
            });
    }

    private function authorizedSupportMessages(User $staff)
    {
        return ChatMessage::query()->whereHas('conversation', function ($query) use ($staff): void {
            $query->where('conversation_type', 'support');
            $this->applyAuthorization($query, $staff);
        });
    }

    private function applyAuthorization($query, User $staff): void
    {
        if ($staff->hasRole('agent')) {
            $query->where(fn ($query) => $query
                ->where('status', '!=', 'resolved')
                ->orWhere('assigned_to', $staff->id));
        }
    }

    private function assertStaff(User $staff): void
    {
        if (! $staff->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('Only staff may use Support Messenger.');
        }
    }
}
