<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrahmaPlayRequest extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'game_id',
        'points_to_load',
        'balance_at_submission',
        'status',
        'game_username',
        'game_password',
        'rejection_note',
        'processed_by',
        'verified_by',
        'processed_at',
        'verified_at',
        'debited_at',
        'balance_before_debit',
        'balance_after_debit',
    ];

    protected $casts = [
        'points_to_load' => 'decimal:2',
        'balance_at_submission' => 'decimal:2',
        'processed_at' => 'datetime',
        'verified_at' => 'datetime',
        'debited_at' => 'datetime',
        'balance_before_debit' => 'decimal:2',
        'balance_after_debit' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($request) {
            if ($request->reference) {
                return;
            }

            do {
                $reference = 'BRPLAY-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            } while (self::where('reference', $reference)->exists());

            $request->reference = $reference;
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

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
