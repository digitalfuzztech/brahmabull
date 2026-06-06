<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;

class NotificationService
{
    public static function notifyAdminsAndAgents(
        string $type,
        string $title,
        string $message,
        ?string $actionText = null,
        ?string $actionUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $gameId = null,
        ?int $createdBy = null,
    ): void {

        $users = User::role([
            'admin',
            'agent'
        ])->get();

        foreach ($users as $user) {

            Notification::create([

                'user_id' => $user->id,

                'type' => $type,

                'title' => $title,

                'message' => $message,

                'action_text' => $actionText,

                'action_url' => $actionUrl,

                'entity_type' => $entityType,

                'entity_id' => $entityId,

                'game_id' => $gameId,

                'created_by' => $createdBy,
            ]);
        }
    }
}
