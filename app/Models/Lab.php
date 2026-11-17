<?php

namespace App\Models;

use Database\Factories\LabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $code
 * @property string|null $notes
 * @property int $row_count
 * @property int $shelves_per_row
 * @property int $levels_per_shelf
 * @property int $filter_interval
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'name', 'code', 'notes', 'row_count', 'shelves_per_row', 'levels_per_shelf'])]
class Lab extends Model
{
    /** @use HasFactory<LabFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<LabRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(LabRow::class);
    }

    /**
     * @return HasMany<Sample, $this>
     */
    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    /**
     * @return HasMany<Filter, $this>
     */
    public function filters(): HasMany
    {
        return $this->hasMany(Filter::class);
    }

    public function levelsCount(): int
    {
        return $this->row_count * $this->shelves_per_row * $this->levels_per_shelf;
    }
}
