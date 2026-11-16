<?php

namespace App\Models;

use Database\Factories\SampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lab_id
 * @property int $lab_level_id
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lab_id', 'lab_level_id', 'code'])]
class Sample extends Model
{
    /** @use HasFactory<SampleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lab, $this>
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * @return BelongsTo<LabLevel, $this>
     */
    public function labLevel(): BelongsTo
    {
        return $this->belongsTo(LabLevel::class);
    }

    /**
     * @return HasMany<Observation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }
}
