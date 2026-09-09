<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinRewardClaim extends Model
{
    protected $guarded = [];

    protected $casts = ['processed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function spin()
    {
        return $this->belongsTo(SpinWheelSpin::class, 'spin_id');
    }

    public function offer()
    {
        return $this->belongsTo(SpinWheelOffer::class);
    }
}
