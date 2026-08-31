<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrahmaDeposit extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'wallet_id',
        'wallet_type',
        'wallet_name',
        'wallet_account_identifier',
        'amount',
        'proof_image',
        'status',
        'load_balance',
        'balance_before_credit',
        'balance_after_credit',
        'processed_by',
        'verified_by',
        'processed_at',
        'verified_at',
        'credited_at',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'load_balance' => 'decimal:2',
        'balance_before_credit' => 'decimal:2',
        'balance_after_credit' => 'decimal:2',
        'processed_at' => 'datetime',
        'verified_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($deposit) {
            if ($deposit->reference) {
                return;
            }

            do {
                $reference = 'BRDEP-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            } while (self::where('reference', $reference)->exists());

            $deposit->reference = $reference;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
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
