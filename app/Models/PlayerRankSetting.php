<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerRankSetting extends Model
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    protected $fillable = [
        'mode',
    ];
}
