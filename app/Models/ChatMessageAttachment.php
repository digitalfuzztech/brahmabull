<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'media_type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'width',
        'height',
        'duration',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
