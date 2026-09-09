<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinWheelAssignment extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_featured' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function offer()
    {
        return $this->belongsTo(SpinWheelOffer::class, 'offer_id');
    }
}
