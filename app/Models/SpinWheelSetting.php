<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinWheelSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean', 'launcher_enabled' => 'boolean', 'celebration_enabled' => 'boolean',
        'show_recent_win' => 'boolean', 'featured_labels' => 'array',
        'try_again_chance' => 'integer', 'free_spin_chance' => 'integer',
        'bonus_points_chance' => 'integer', 'sajilo_points_chance' => 'integer', 'badge_chance' => 'integer',
    ];
}
