<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSupportEvent extends Model
{
    protected $fillable = [
        'conversation_id',
        'player_id',
        'event_type',
        'related_type',
        'related_id',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
