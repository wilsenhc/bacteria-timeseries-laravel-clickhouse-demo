<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    public function canManageLabs(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function canManageOrganization(): bool
    {
        return $this === self::Owner;
    }
}
