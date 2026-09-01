<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChatConversation extends Model
{
    public const OPEN_STATUSES = ['bot', 'waiting', 'active'];

    protected $fillable = [
        'reference',
        'conversation_type',
        'player_id',
        'status',
        'name',
        'created_by',
        'direct_key',
        'is_archived',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'human_requested_at',
        'first_staff_response_at',
        'last_message_at',
        'last_player_message_at',
        'last_staff_message_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'human_requested_at' => 'datetime',
            'first_staff_response_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_player_message_at' => 'datetime',
            'last_staff_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChatConversation $conversation): void {
            $conversation->conversation_type ??= 'support';

            if ($conversation->conversation_type === 'support' && ! $conversation->player_id) {
                throw new InvalidArgumentException('A support conversation requires a player.');
            }

            if ($conversation->conversation_type !== 'support' && $conversation->player_id !== null) {
                throw new InvalidArgumentException('Internal conversations cannot have a player.');
            }

            if ($conversation->conversation_type === 'internal_direct'
                && ($conversation->direct_key === null || $conversation->name !== null)) {
                throw new InvalidArgumentException('A direct conversation requires a direct key and cannot have a name.');
            }

            if ($conversation->conversation_type === 'internal_group'
                && (blank($conversation->name) || ! $conversation->created_by || $conversation->direct_key !== null)) {
                throw new InvalidArgumentException('A group conversation requires a name and creator.');
            }

            if ($conversation->conversation_type === 'support') {
                $conversation->status ??= 'bot';
                $conversation->name = null;
                $conversation->direct_key = null;
            } else {
                $conversation->status = null;
            }

            if ($conversation->reference) {
                return;
            }

            do {
                $reference = 'CHAT-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
            } while (self::where('reference', $reference)->exists());

            $conversation->reference = $reference;
        });
    }

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigningStaff()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function supportEvents()
    {
        return $this->hasMany(ChatSupportEvent::class, 'conversation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(ChatConversationParticipant::class, 'conversation_id');
    }

    public function activeParticipants()
    {
        return $this->participants()->whereNull('left_at');
    }
}
