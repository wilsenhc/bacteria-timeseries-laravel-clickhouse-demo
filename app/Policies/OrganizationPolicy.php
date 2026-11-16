<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->role($user, $organization) !== null;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->role($user, $organization)?->canManageOrganization() ?? false;
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function manageLabs(User $user, Organization $organization): bool
    {
        return $this->role($user, $organization)?->canManageLabs() ?? false;
    }

    private function role(User $user, Organization $organization): ?OrganizationRole
    {
        return $user->roleIn($organization);
    }
}
