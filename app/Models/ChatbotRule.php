<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotRule extends Model
{
    protected $fillable = [
        'name',
        'trigger_type',
        'trigger_value',
        'response_text',
        'action_type',
        'action_config',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_value' => 'array',
            'action_config' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
