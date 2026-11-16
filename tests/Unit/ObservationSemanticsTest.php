<?php

use App\Enums\GrowthLevel;
use App\Enums\OrganizationRole;

test('growth levels rank from high to none', function () {
    expect(GrowthLevel::High->rank())->toBeGreaterThan(GrowthLevel::Medium->rank())
        ->and(GrowthLevel::Medium->rank())->toBeGreaterThan(GrowthLevel::Low->rank())
        ->and(GrowthLevel::Low->rank())->toBeGreaterThan(GrowthLevel::None->rank())
        ->and(GrowthLevel::High->isPresent())->toBeTrue()
        ->and(GrowthLevel::Medium->isPresent())->toBeTrue()
        ->and(GrowthLevel::Low->isPresent())->toBeTrue()
        ->and(GrowthLevel::None->isPresent())->toBeFalse();
});

test('organization roles expose the permission matrix', function () {
    expect(OrganizationRole::Owner->canManageOrganization())->toBeTrue()
        ->and(OrganizationRole::Admin->canManageOrganization())->toBeFalse()
        ->and(OrganizationRole::Member->canManageOrganization())->toBeFalse()
        ->and(OrganizationRole::Owner->canManageLabs())->toBeTrue()
        ->and(OrganizationRole::Admin->canManageLabs())->toBeTrue()
        ->and(OrganizationRole::Member->canManageLabs())->toBeFalse();
});
