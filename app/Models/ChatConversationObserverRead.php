<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversationObserverRead extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
