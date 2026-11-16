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
 * @property int $bacteria_id
 * @property GrowthLevel|null $growth_level
 * @property int|null $count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['observation_id', 'bacteria_id', 'growth_level', 'count'])]
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
            'growth_level' => GrowthLevel::class,
            'count' => 'integer',
        ];
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
