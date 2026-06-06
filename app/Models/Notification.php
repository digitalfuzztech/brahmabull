<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [

        'user_id',

        'type',

        'title',

        'message',

        'action_text',

        'action_url',

        'entity_type',

        'entity_id',

        'game_id',

        'is_read',

        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
