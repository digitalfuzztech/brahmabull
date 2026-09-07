<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversationParticipant extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'participant_role',
        'joined_at',
        'left_at',
        'last_read_at',
        'last_read_message_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_read_at' => 'datetime',
            'last_read_message_id' => 'integer',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
