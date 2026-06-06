<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Game extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'game_url',
        'description',
        'is_active',
    ];

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($game) {
            if (!$game->slug) {
                $game->slug = Str::slug($game->name);
            }
        });
    }
}
