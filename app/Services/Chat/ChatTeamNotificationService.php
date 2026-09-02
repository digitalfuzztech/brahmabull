<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\Notification;
use App\Models\User;

class ChatTeamNotificationService
{
    public function notifyAddedToGroup(ChatConversation $conversation, User $member, User $owner): void
    {
        Notification::create([
            'user_id' => $member->id,
            'type' => 'chat_team_group_added',
            'title' => 'Added to Team Group',
            'message' => 'You were added to '.$conversation->name.'.',
            'action_text' => 'Open Team Inbox',
            'action_url' => route('agent.inbox', ['domain' => 'team']),
            'entity_type' => ChatConversation::class,
            'entity_id' => $conversation->id,
            'created_by' => $owner->id,
            'is_read' => false,
        ]);
    }
}
