<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinWheelOffer extends Model
{
    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'notify_staff' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function type()
    {
        return $this->belongsTo(SpinWheelOfferType::class, 'offer_type_id');
    }

    public function assignments()
    {
        return $this->hasMany(SpinWheelAssignment::class, 'offer_id');
    }

    public function spins()
    {
        return $this->hasMany(SpinWheelSpin::class, 'offer_id');
    }
}
