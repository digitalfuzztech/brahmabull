<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use LogicException;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'reply_to_message_id',
        'sender_type',
        'sender_id',
        'message_type',
        'body',
        'metadata',
        'read_by_player_at',
        'read_by_staff_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_by_player_at' => 'datetime',
            'read_by_staff_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'reply_to_message_id');
    }

    public function attachments()
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }

    public function reactions()
    {
        return $this->hasMany(ChatMessageReaction::class, 'message_id');
    }

    public function toPlayerSafeArray(): array
    {
        $conversationType = $this->relationLoaded('conversation')
            ? $this->conversation?->conversation_type
            : $this->conversation()->value('conversation_type');

        if ($conversationType !== 'support') {
            throw new LogicException('Internal messages cannot be serialized as player support messages.');
        }

        $isPlayer = $this->sender_type === 'player';

        return [
            'id' => $this->id,
            'body' => $this->body,
            'message_type' => $this->message_type,
            'metadata' => Arr::only($this->metadata ?? [], ['options', 'related_type', 'related_id']),
            'created_at' => $this->created_at?->toISOString(),
            'is_player' => $isPlayer,
            'display_name' => $isPlayer ? 'You' : 'Brahmabull Support Team',
        ];
    }

    public function toInternalArray(): array
    {
        $sender = $this->sender;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'message_type' => $this->message_type,
            'metadata' => $this->metadata,
            'reply_to_message_id' => $this->reply_to_message_id,
            'created_at' => $this->created_at?->toISOString(),
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => $sender->name,
                'role' => $sender->hasRole('admin') ? 'admin' : 'agent',
            ] : null,
        ];
    }
}
