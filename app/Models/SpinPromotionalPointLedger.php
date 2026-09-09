<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinPromotionalPointLedger extends Model
{
    protected $guarded = [];

    protected $casts = ['awarded_at' => 'datetime', 'processed_at' => 'datetime'];

    public function spin()
    {
        return $this->belongsTo(SpinWheelSpin::class);
    }
}
