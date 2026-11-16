<?php

namespace App\Models;

use Database\Factories\LabRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lab_id
 * @property int $number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lab_id', 'number'])]
class LabRow extends Model
{
    /** @use HasFactory<LabRowFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lab, $this>
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * @return HasMany<LabShelf, $this>
     */
    public function shelves(): HasMany
    {
        return $this->hasMany(LabShelf::class);
    }
}
