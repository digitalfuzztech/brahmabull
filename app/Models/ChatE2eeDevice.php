<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatE2eeDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'public_encryption_key',
        'public_signing_key',
        'key_fingerprint',
        'trusted_at',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'trusted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversationKeys()
    {
        return $this->hasMany(ChatE2eeConversationKey::class, 'device_id');
    }

    public function isTrustedAndActive(): bool
    {
        return $this->trusted_at !== null && $this->revoked_at === null;
    }
}
