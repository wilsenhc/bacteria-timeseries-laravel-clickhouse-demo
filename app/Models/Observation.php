<?php

namespace App\Models;

use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sample_id
 * @property Carbon|null $observed_at
 * @property int|null $iso_year
 * @property int|null $iso_week
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sample_id', 'observed_at', 'iso_year', 'iso_week'])]
class Observation extends Model
{
    /** @use HasFactory<ObservationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'iso_year' => 'integer',
            'iso_week' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sample, $this>
     */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    /**
     * @return HasMany<BacteriaObservation, $this>
     */
    public function bacteriaObservations(): HasMany
    {
        return $this->hasMany(BacteriaObservation::class);
    }

    /**
     * Compute the ISO year and week from the observation's sample organization.
     */
    protected static function booted(): void
    {
        static::creating(function (Observation $observation): void {
            if ($observation->iso_year !== null && $observation->iso_week !== null) {
                return;
            }

            if ($observation->observed_at === null) {
                return;
            }

            $timezone = $observation->sample?->lab?->organization->timezone
                ?? config('app.timezone');

            $local = $observation->observed_at->copy()->setTimezone($timezone);

            $observation->iso_year = $local->isoWeekYear;
            $observation->iso_week = $local->isoWeek;
        });
    }
}
