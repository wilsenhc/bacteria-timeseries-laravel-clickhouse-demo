<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\FilterObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $filter_id
 * @property Carbon|null $observed_at
 * @property string|null $week_start
 * @property int $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['filter_id', 'observed_at', 'week_start', 'value'])]
class FilterObservation extends Model
{
    /** @use HasFactory<FilterObservationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Filter, $this>
     */
    public function filter(): BelongsTo
    {
        return $this->belongsTo(Filter::class);
    }

    /**
     * Fill the reading's Monday from the filter's organization timezone.
     */
    protected static function booted(): void
    {
        static::creating(function (FilterObservation $observation): void {
            if ($observation->week_start !== null) {
                return;
            }

            if ($observation->observed_at === null) {
                return;
            }

            $timezone = $observation->filter?->lab?->organization->timezone
                ?? config('app.timezone');

            $observation->week_start = $observation->observed_at
                ->copy()
                ->setTimezone($timezone)
                ->startOfWeek(CarbonInterface::MONDAY)
                ->toDateString();
        });
    }
}
