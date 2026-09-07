<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotMenuItem extends Model
{
    protected $fillable = [
        'label', 'action_type', 'response_text', 'sort_order', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
