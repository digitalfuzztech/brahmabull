<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletAgent extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'created_by',
    ];

    public function types()
    {
        return $this->hasMany(WalletType::class);
    }
}
