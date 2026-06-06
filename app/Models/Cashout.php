<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashout extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'game_id',
        'game_account_id',
        'amount',
        'wallet_id',
        'wallet_type',
        'wallet_address',
        'qr_image',
        'payment_proof',
        'status',
        'verified_by',
        'verified_at',
        'paid_at',
        'remarks',
    ];
    protected $casts = [
        'paid_at' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

   // public function gameAccount()
  //  {
   //     return $this->belongsTo(GameAccount::class);
 //   }
    public function gameAccount()
    {
        return $this->belongsTo(
            \App\Models\GameAccount::class,
            'game_id',
            'game_id'
        );
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
