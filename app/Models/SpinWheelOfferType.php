<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinWheelOfferType extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function offers()
    {
        return $this->hasMany(SpinWheelOffer::class, 'offer_type_id');
    }
}
