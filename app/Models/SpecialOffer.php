<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class SpecialOffer extends Model
{
    use SoftDeletes;

    public const MAX_OFFERS = 4;

    protected $fillable = [
        'name',
        'description',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (static::query()->lockForUpdate()->count() >= static::MAX_OFFERS) {
                throw ValidationException::withMessages([
                    'offerLimit' => 'Maximum 4 offers reached.',
                ]);
            }
        });
    }

    public function scopeCoveringDate(Builder $query, CarbonInterface|string $date): Builder
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date);
    }

    public function scopeActiveOnDate(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->coveringDate($date);
    }
}
