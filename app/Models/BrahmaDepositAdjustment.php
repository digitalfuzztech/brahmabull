<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrahmaDepositAdjustment extends Model
{
    protected $fillable = [
        'brahma_deposit_id',
        'old_load_balance',
        'new_load_balance',
        'delta',
        'admin_id',
    ];

    protected $casts = [
        'old_load_balance' => 'decimal:2',
        'new_load_balance' => 'decimal:2',
        'delta' => 'decimal:2',
    ];

    public function deposit()
    {
        return $this->belongsTo(BrahmaDeposit::class, 'brahma_deposit_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
