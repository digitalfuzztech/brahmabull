<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageReaction extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
        'encrypted_reaction',
        'encryption_version',
        'key_version',
    ];

    protected function casts(): array
    {
        return [
            'encryption_version' => 'integer',
            'key_version' => 'integer',
        ];
    }

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
