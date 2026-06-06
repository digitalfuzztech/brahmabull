<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_user_id',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class,'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class,'referred_user_id');
    }
}
