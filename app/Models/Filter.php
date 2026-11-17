<?php

namespace App\Models;

use App\Support\ObservationWeek;
use Database\Factories\FilterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lab_id
 * @property int $sample_id
 * @property Carbon|null $placed_at
 * @property int|null $iso_year
 * @property int|null $iso_week
 * @property string|null $week_start
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lab_id', 'sample_id', 'placed_at', 'iso_year', 'iso_week', 'week_start'])]
class Filter extends Model
{
    /** @use HasFactory<FilterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'iso_year' => 'integer',
            'iso_week' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lab, $this>
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * @return BelongsTo<Sample, $this>
     */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    /**
     * Fill the placement's ISO week and Monday from the sample's organization timezone.
     */
    protected static function booted(): void
    {
        static::creating(function (Filter $filter): void {
            if ($filter->iso_year !== null && $filter->iso_week !== null && $filter->week_start !== null) {
                return;
            }

            if ($filter->placed_at === null) {
                return;
            }

            $timezone = $filter->sample?->lab?->organization->timezone
                ?? config('app.timezone');

            $week = ObservationWeek::for($filter->placed_at, $timezone);

            $filter->iso_year = $week->isoYear;
            $filter->iso_week = $week->isoWeek;
            $filter->week_start = $week->startDate;
        });
    }
}
