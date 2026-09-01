<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatSupportEvent;
use App\Models\Notification;
use App\Models\User;

class ChatSupportNotificationService
{
    public function notifyHumanSupportRequested(ChatSupportEvent $event, User $player): void
    {
        $this->notifyAdmins(
            $event,
            $player,
            'chat_human_support_requested',
            'New Support Request',
            $this->playerIdentity($player).' requested support.',
        );
    }

    public function notifyConversationAssigned(ChatConversation $conversation, User $agent, User $admin): Notification
    {
        return Notification::create([
            'user_id' => $agent->id,
            'type' => 'chat_conversation_assigned',
            'title' => 'Support Conversation Assigned',
            'message' => 'A support conversation has been assigned to you.',
            'action_text' => 'Open Inbox',
            'action_url' => route('agent.inbox'),
            'entity_type' => ChatConversation::class,
            'entity_id' => $conversation->id,
            'created_by' => $admin->id,
            'is_read' => false,
        ]);
    }

    public function notifyReminderCreated(ChatSupportEvent $event, User $player, string $subject): void
    {
        // Phase 1 records have no explicit pending-request assignee, so reminders safely target Admin only.
        $this->notifyAdmins(
            $event,
            $player,
            'chat_support_reminder',
            'Support Reminder',
            $this->playerIdentity($player).' requested an update for '.$subject.'.',
        );
    }

    private function notifyAdmins(
        ChatSupportEvent $event,
        User $player,
        string $type,
        string $title,
        string $message,
    ): void {
        User::role('admin')->select('users.id')->each(function (User $admin) use ($event, $player, $type, $title, $message): void {
            Notification::firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'type' => $type,
                    'entity_type' => ChatSupportEvent::class,
                    'entity_id' => $event->id,
                ],
                [
                    'title' => $title,
                    'message' => $message,
                    'action_text' => 'Open Inbox',
                    'action_url' => route('admin.inbox'),
                    'created_by' => $player->id,
                    'is_read' => false,
                ],
            );
        });
    }

    private function playerIdentity(User $player): string
    {
        return $player->name.' (@'.$player->username.')';
    }
}
