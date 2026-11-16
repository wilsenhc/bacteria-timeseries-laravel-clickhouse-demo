<?php

namespace App\Models;

use Database\Factories\LabLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lab_shelf_id
 * @property int $number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lab_shelf_id', 'number'])]
class LabLevel extends Model
{
    /** @use HasFactory<LabLevelFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LabShelf, $this>
     */
    public function labShelf(): BelongsTo
    {
        return $this->belongsTo(LabShelf::class);
    }

    /**
     * @return HasMany<Sample, $this>
     */
    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }
}
