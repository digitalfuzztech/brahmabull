<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletType extends Model
{
    protected $fillable = [
        'wallet_agent_id',
        'name',
        'slug',
    ];

    public function agent()
    {
        return $this->belongsTo(WalletAgent::class, 'wallet_agent_id');
    }

    public function wallets()
    {
        return $this->hasMany(
            Wallet::class,
            'wallet_type_id'
        );
    }
}
