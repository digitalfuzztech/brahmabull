<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpinAttemptGrant extends Model
{
    protected $guarded = [];

    protected $casts = ['granted_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
