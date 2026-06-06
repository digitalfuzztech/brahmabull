<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'game_id',
        'wallet_id',
        'wallet_type',
        'amount',
        'proof_image',
        'status',
        'admin_notes',
        'verified_by',
        'verified_at',
        'original_verified_by',
        'game_points_loaded',
        'bonus_points_added',
    ];
    protected $casts = [
        'verified_at' => 'datetime',
    ];
    protected static function booted()
    {
        static::creating(function ($deposit) {

            $deposit->reference =
                'DEP-' .
                now()->format('Ymd') .
                '-' .
                strtoupper(substr(uniqid(), -5));

        });
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
    public function gameAccount()
    {
        return $this->belongsTo(
            \App\Models\GameAccount::class,
            'game_id',
            'game_id'
        );
    }
}
