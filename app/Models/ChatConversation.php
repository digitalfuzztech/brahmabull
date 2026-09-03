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
        'channel_key',
        'is_archived',
        'encryption_mode',
        'e2ee_enabled_at',
        'current_key_version',
        'e2ee_rotation_required_at',
        'e2ee_disable_requested_by',
        'e2ee_disable_requested_at',
        'e2ee_disabled_at',
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
            'e2ee_enabled_at' => 'datetime',
            'current_key_version' => 'integer',
            'e2ee_rotation_required_at' => 'datetime',
            'e2ee_disable_requested_at' => 'datetime',
            'e2ee_disabled_at' => 'datetime',
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

            if ($conversation->conversation_type === 'internal_channel'
                && (blank($conversation->name) || blank($conversation->channel_key) || $conversation->direct_key !== null)) {
                throw new InvalidArgumentException('An internal channel requires a name and channel key.');
            }

            if ($conversation->conversation_type === 'support') {
                $conversation->status ??= 'bot';
                $conversation->name = null;
                $conversation->direct_key = null;
                $conversation->channel_key = null;
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

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
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

    public function e2eeConversationKeys()
    {
        return $this->hasMany(ChatE2eeConversationKey::class, 'conversation_id');
    }

    public function e2eeDisableRequester()
    {
        return $this->belongsTo(User::class, 'e2ee_disable_requested_by');
    }

    public function observerReads()
    {
        return $this->hasMany(ChatConversationObserverRead::class, 'conversation_id');
    }
}
