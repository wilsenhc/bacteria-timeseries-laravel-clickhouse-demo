<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $address
 * @property string $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'address', 'timezone'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this, OrganizationMembership, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationMembership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Lab, $this>
     */
    public function labs(): HasMany
    {
        return $this->hasMany(Lab::class);
    }

    public function roleFor(User $user): ?OrganizationRole
    {
        $membership = $this->users()->whereKey($user->id)->first();

        return $membership?->pivot->role;
    }
}
