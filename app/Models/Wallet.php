<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'wallet_type_id',
        'wallet_agent_id',
        'type',
        'name',
        'account_identifier',
        'qr_image',
        'is_active',
        'created_by',
        'updated_by',
    ];
    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }
    public function walletType()
    {
        return $this->belongsTo(
            WalletType::class,
            'wallet_type_id'
        );
    }
    public function walletAgent()
    {
        return $this->belongsTo(\App\Models\WalletAgent::class, 'wallet_agent_id');
    }
    public function creator()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'created_by'
        );
    }

    public function updater()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'updated_by'
        );
    }
}
