<?php

namespace App\Models;

use App\Enums\GrowthLevel;
use Database\Factories\BacteriaObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $observation_id
 * @property int|null $sample_id
 * @property Carbon|null $observed_at
 * @property int $bacteria_id
 * @property GrowthLevel|null $growth_level
 * @property int|null $count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['observation_id', 'sample_id', 'observed_at', 'bacteria_id', 'growth_level', 'count'])]
class BacteriaObservation extends Model
{
    /** @use HasFactory<BacteriaObservationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sample_id' => 'integer',
            'observed_at' => 'datetime',
            'growth_level' => GrowthLevel::class,
            'count' => 'integer',
        ];
    }

    /**
     * Mirror the parent observation's sample and time for the state index.
     */
    protected static function booted(): void
    {
        static::creating(function (BacteriaObservation $observation): void {
            if ($observation->sample_id !== null && $observation->observed_at !== null) {
                return;
            }

            $parent = $observation->observation;

            $observation->sample_id ??= $parent?->sample_id;
            $observation->observed_at ??= $parent?->observed_at;
        });
    }

    /**
     * @return BelongsTo<Observation, $this>
     */
    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    /**
     * @return BelongsTo<Bacteria, $this>
     */
    public function bacteria(): BelongsTo
    {
        return $this->belongsTo(Bacteria::class);
    }
}
