<?php

namespace App\Models;

use Database\Factories\LabShelfFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lab_row_id
 * @property int $number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lab_row_id', 'number'])]
class LabShelf extends Model
{
    /** @use HasFactory<LabShelfFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LabRow, $this>
     */
    public function labRow(): BelongsTo
    {
        return $this->belongsTo(LabRow::class);
    }

    /**
     * @return HasMany<LabLevel, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(LabLevel::class);
    }
}
