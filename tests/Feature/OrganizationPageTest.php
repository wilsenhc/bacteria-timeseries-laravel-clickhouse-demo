<?php

use App\Enums\OrganizationRole;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\Sample;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $this->get(route('organizations.index'))->assertRedirect(route('login'));
});

test('users only see organizations they belong to', function () {
    $user = User::factory()->create();
    $memberOrganization = Organization::factory()->create(['name' => 'Member Org']);
    $memberOrganization->users()->attach($user->id, ['role' => OrganizationRole::Owner->value]);
    Organization::factory()->create(['name' => 'Other Org']);

    $this->actingAs($user)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/Index')
            ->has('organizations', 1)
            ->where('organizations.0.name', 'Member Org')
            ->where('organizations.0.role', 'owner')
            ->where('organizations.0.labs_count', 0));
});

test('a user can create an organization, becomes its owner, and selects it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'New Org',
            'address' => '123 Main St',
            'timezone' => 'America/Sao_Paulo',
        ])
        ->assertRedirect(route('organizations.index'));

    $organization = Organization::query()->where('name', 'New Org')->firstOrFail();

    expect($user->roleIn($organization))->toBe(OrganizationRole::Owner)
        ->and(session('selected_organization_id'))->toBe($organization->id);
});

test('organization validation rejects an invalid timezone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'Bad Org',
            'address' => '123 Main St',
            'timezone' => 'Not/AZone',
        ])
        ->assertSessionHasErrors('timezone');
});

test('an owner can update the organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

    $this->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => 'Updated Org',
            'address' => '456 New St',
            'timezone' => 'UTC',
        ])
        ->assertRedirect(route('organizations.index'));

    expect($organization->fresh()->name)->toBe('Updated Org');
});

test('an admin cannot update or delete the organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['role' => OrganizationRole::Admin->value]);

    $this->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => 'Nope',
            'address' => 'Nope',
            'timezone' => 'UTC',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('organizations.destroy', $organization))
        ->assertForbidden();
});

test('a non member cannot edit an organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->get(route('organizations.edit', $organization))
        ->assertForbidden();
});

test('a stale selection falls back to the first organization', function () {
    $user = User::factory()->create();
    $first = Organization::factory()->create(['name' => 'Alpha Org']);
    $second = Organization::factory()->create(['name' => 'Zulu Org']);
    $first->users()->attach($user->id, ['role' => OrganizationRole::Member->value]);
    $second->users()->attach($user->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($user)
        ->withSession(['selected_organization_id' => $second->id + 1000])
        ->get(route('labs.index'))
        ->assertOk();

    expect(session('selected_organization_id'))->toBe($first->id);
});

test('a user can switch the selected organization', function () {
    $user = User::factory()->create();
    $first = Organization::factory()->create(['name' => 'First Org']);
    $second = Organization::factory()->create(['name' => 'Second Org']);
    $first->users()->attach($user->id, ['role' => OrganizationRole::Member->value]);
    $second->users()->attach($user->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($user)
        ->withSession(['selected_organization_id' => $first->id])
        ->post(route('organizations.select'), ['organization_id' => $second->id])
        ->assertRedirect(route('labs.index'));

    expect(session('selected_organization_id'))->toBe($second->id);
});

test('a user cannot switch to an organization they do not belong to', function () {
    $user = User::factory()->create();
    $other = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.select'), ['organization_id' => $other->id])
        ->assertForbidden();

    expect(session('selected_organization_id'))->toBeNull();
});

test('deleting the selected organization clears the selection', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

    $this->actingAs($user)
        ->withSession(['selected_organization_id' => $organization->id])
        ->delete(route('organizations.destroy', $organization))
        ->assertRedirect(route('organizations.index'));

    expect(session('selected_organization_id'))->toBeNull();
});

test('deleting an organization cascades to labs and samples', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['role' => OrganizationRole::Owner->value]);
    $lab = createLabWithHierarchy($organization);
    createSample($lab);

    $this->actingAs($user)
        ->delete(route('organizations.destroy', $organization))
        ->assertRedirect(route('organizations.index'));

    expect(Organization::query()->count())->toBe(0)
        ->and(Lab::query()->count())->toBe(0)
        ->and(Sample::query()->count())->toBe(0);
});
