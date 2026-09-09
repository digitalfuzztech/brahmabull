<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class SpinWheelSpin extends Model
{
    private const IMMUTABLE_COLUMNS = [
        'request_token', 'user_id', 'attempt_grant_id', 'spin_number', 'wheel_number', 'wheel_slot', 'offer_id',
        'offer_snapshot_name', 'offer_snapshot_value', 'offer_snapshot_type', 'offer_snapshot_category',
        'offer_snapshot_score', 'offer_snapshot_metadata', 'spun_at',
    ];

    protected $guarded = [];

    protected $casts = ['offer_snapshot_metadata' => 'array', 'spun_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $spin): void {
            foreach (self::IMMUTABLE_COLUMNS as $column) {
                if ($spin->isDirty($column)) {
                    throw new LogicException('Persisted Spin Wheel outcomes are immutable.');
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function offer()
    {
        return $this->belongsTo(SpinWheelOffer::class);
    }

    public function grant()
    {
        return $this->belongsTo(SpinAttemptGrant::class, 'attempt_grant_id');
    }

    public function entitlement()
    {
        return $this->hasOne(SpinRewardEntitlement::class, 'spin_id');
    }

    public function claim()
    {
        return $this->hasOne(SpinRewardClaim::class, 'spin_id');
    }

    public function promotionalPoints()
    {
        return $this->hasOne(SpinPromotionalPointLedger::class, 'spin_id');
    }
}
