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

function labOrganizationWithRole(User $user, OrganizationRole $role): Organization
{
    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['role' => $role->value]);

    return $organization;
}

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();
    $lab = createLabWithHierarchy($organization);

    $this->get(route('labs.show', $lab))
        ->assertRedirect(route('login'));
});

test('the labs page lists only the selected organization labs', function () {
    $user = User::factory()->create();
    $selected = labOrganizationWithRole($user, OrganizationRole::Owner);
    $other = labOrganizationWithRole($user, OrganizationRole::Owner);
    $selectedLab = createLabWithHierarchy($selected);
    createLabWithHierarchy($other);

    $this->actingAs($user)
        ->withSession(['selected_organization_id' => $selected->id])
        ->get(route('labs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Index')
            ->where('organization.id', $selected->id)
            ->has('labs', 1)
            ->where('labs.0.id', $selectedLab->id)
            ->where('canManage', true));
});

test('the labs page renders without an organization for a user with no memberships', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('labs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Index')
            ->where('organization', null)
            ->has('labs', 0)
            ->where('canManage', false));
});

test('an owner can create a lab with a generated hierarchy under the selected organization', function () {
    $user = User::factory()->create();
    $selected = labOrganizationWithRole($user, OrganizationRole::Owner);
    $other = labOrganizationWithRole($user, OrganizationRole::Owner);

    $this->actingAs($user)
        ->withSession(['selected_organization_id' => $selected->id])
        ->post(route('labs.store'), [
            'name' => 'Lab X',
            'code' => 'LAB-X',
            'notes' => 'Notes',
            'row_count' => 2,
            'shelves_per_row' => 3,
            'levels_per_shelf' => 4,
        ])
        ->assertRedirect();

    $lab = Lab::query()->where('name', 'Lab X')->firstOrFail();

    expect($lab->organization_id)->toBe($selected->id)
        ->and($lab->organization_id)->not->toBe($other->id)
        ->and($lab->row_count)->toBe(2)
        ->and($lab->rows()->count())->toBe(2)
        ->and($lab->rows()->first()->shelves()->count())->toBe(3)
        ->and($lab->rows()->first()->shelves()->first()->levels()->count())->toBe(4)
        ->and($lab->samples()->count())->toBe(0);
});

test('a member cannot create a lab', function () {
    $user = User::factory()->create();
    labOrganizationWithRole($user, OrganizationRole::Member);

    $this->actingAs($user)
        ->post(route('labs.store'), [
            'name' => 'Nope',
            'row_count' => 1,
            'shelves_per_row' => 1,
            'levels_per_shelf' => 1,
        ])
        ->assertForbidden();
});

test('a non member cannot view a lab', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $lab = createLabWithHierarchy($organization);

    $this->actingAs($user)
        ->get(route('labs.show', $lab))
        ->assertForbidden();
});

test('a lab outside the selected organization returns not found', function () {
    $user = User::factory()->create();
    $selected = labOrganizationWithRole($user, OrganizationRole::Owner);
    $other = labOrganizationWithRole($user, OrganizationRole::Owner);
    $otherLab = createLabWithHierarchy($other);

    $this->actingAs($user)
        ->withSession(['selected_organization_id' => $selected->id])
        ->get(route('labs.show', $otherLab))
        ->assertNotFound();
});

test('an owner can update lab metadata but not the fixed structure', function () {
    $user = User::factory()->create();
    $organization = labOrganizationWithRole($user, OrganizationRole::Owner);
    $lab = createLabWithHierarchy($organization, 2, 2, 2);

    $this->actingAs($user)
        ->patch(route('labs.update', $lab), [
            'name' => 'Renamed Lab',
            'code' => 'RENAMED',
            'notes' => 'Updated',
            'row_count' => 9,
            'shelves_per_row' => 9,
            'levels_per_shelf' => 9,
        ])
        ->assertRedirect(route('labs.show', $lab));

    $lab->refresh();

    expect($lab->name)->toBe('Renamed Lab')
        ->and($lab->row_count)->toBe(2)
        ->and($lab->shelves_per_row)->toBe(2)
        ->and($lab->levels_per_shelf)->toBe(2)
        ->and($lab->rows()->count())->toBe(2);
});

test('clearing a lab code stores null', function () {
    $user = User::factory()->create();
    $organization = labOrganizationWithRole($user, OrganizationRole::Owner);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $lab->forceFill(['code' => 'LAB-X'])->saveQuietly();

    $this->actingAs($user)
        ->patch(route('labs.update', $lab), [
            'name' => $lab->name,
            'code' => '',
            'notes' => '',
        ])
        ->assertRedirect();

    $lab->refresh();

    expect($lab->code)->toBeNull()
        ->and($lab->notes)->toBeNull();
});

test('a lab name must be unique within its organization', function () {
    $user = User::factory()->create();
    $organization = labOrganizationWithRole($user, OrganizationRole::Owner);
    createLabWithHierarchy($organization, 1, 1, 1);
    $existing = $organization->labs()->firstOrFail();

    $this->actingAs($user)
        ->post(route('labs.store'), [
            'name' => $existing->name,
            'row_count' => 1,
            'shelves_per_row' => 1,
            'levels_per_shelf' => 1,
        ])
        ->assertSessionHasErrors('name');
});

test('lab structure counts are limited to twenty', function () {
    $user = User::factory()->create();
    labOrganizationWithRole($user, OrganizationRole::Owner);

    $this->actingAs($user)
        ->post(route('labs.store'), [
            'name' => 'Too Big',
            'row_count' => 21,
            'shelves_per_row' => 1,
            'levels_per_shelf' => 1,
        ])
        ->assertSessionHasErrors('row_count');
});

test('the lab map page returns the hierarchy and week options', function () {
    $user = User::factory()->create();
    $organization = labOrganizationWithRole($user, OrganizationRole::Owner);
    $lab = createLabWithHierarchy($organization, 2, 2, 2);
    createSample($lab);

    $this->actingAs($user)
        ->get(route('labs.show', $lab))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Show')
            ->where('organization.id', $organization->id)
            ->has('weeks')
            ->has('rows', 2)
            ->has('rows.0.shelves', 2)
            ->has('rows.0.shelves.0.levels', 2)
            ->has('rows.0.shelves.0.levels.0.samples', 1)
            ->where('rows.0.shelves.0.levels.0.samples.0.summary.present_count', 0));
});

test('deleting a lab cascades to its samples', function () {
    $user = User::factory()->create();
    $organization = labOrganizationWithRole($user, OrganizationRole::Owner);
    $lab = createLabWithHierarchy($organization);
    createSample($lab);

    $this->actingAs($user)
        ->delete(route('labs.destroy', $lab))
        ->assertRedirect(route('labs.index'));

    expect(Lab::query()->count())->toBe(0)
        ->and(Sample::query()->count())->toBe(0);
});
