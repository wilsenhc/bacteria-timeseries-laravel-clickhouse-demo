<?php

namespace App\Models;

use App\Enums\BacteriaObservationType;
use Database\Factories\BacteriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $short_name
 * @property string $description
 * @property string $color
 * @property string $icon The icon's short name, matching resources/assets/icons/<icon>.svg
 * @property BacteriaObservationType $observation_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'short_name', 'description', 'color', 'icon', 'observation_type'])]
class Bacteria extends Model
{
    /** @use HasFactory<BacteriaFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bacteria';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observation_type' => BacteriaObservationType::class,
        ];
    }

    /**
     * @return HasMany<BacteriaObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(BacteriaObservation::class);
    }
}
