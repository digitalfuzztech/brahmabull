<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'client_attachment_uuid',
        'media_type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'width',
        'height',
        'duration',
        'is_encrypted',
        'encrypted_key',
        'encrypted_metadata',
        'encryption_version',
        'key_version',
        'ciphertext_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'is_encrypted' => 'boolean',
            'encryption_version' => 'integer',
            'key_version' => 'integer',
            'ciphertext_size' => 'integer',
        ];
    }

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
