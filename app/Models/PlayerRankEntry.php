<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerRankEntry extends Model
{
    protected $fillable = [
        'period',
        'rank',
        'player_name',
        'wins',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'wins' => 'decimal:2',
        ];
    }
}
