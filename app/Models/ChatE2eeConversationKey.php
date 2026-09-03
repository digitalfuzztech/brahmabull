<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatE2eeConversationKey extends Model
{
    protected $fillable = [
        'conversation_id',
        'device_id',
        'key_version',
        'wrapped_key',
        'wrapping_algorithm',
        'format_version',
    ];

    protected function casts(): array
    {
        return [
            'key_version' => 'integer',
            'format_version' => 'integer',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function device()
    {
        return $this->belongsTo(ChatE2eeDevice::class, 'device_id');
    }
}
