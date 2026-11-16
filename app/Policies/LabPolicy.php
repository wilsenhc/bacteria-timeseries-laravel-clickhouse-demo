<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\User;

class LabPolicy
{
    public function view(User $user, Lab $lab): bool
    {
        return $this->role($user, $lab->organization) !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->role($user, $organization)?->canManageLabs() ?? false;
    }

    public function update(User $user, Lab $lab): bool
    {
        return $this->role($user, $lab->organization)?->canManageLabs() ?? false;
    }

    public function delete(User $user, Lab $lab): bool
    {
        return $this->update($user, $lab);
    }

    private function role(User $user, Organization $organization): ?OrganizationRole
    {
        return $user->roleIn($organization);
    }
}
