<?php

use App\Enums\GrowthLevel;
use App\Enums\OrganizationRole;
use App\Models\Bacteria;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\Sample;
use App\Models\User;
use App\Support\LabMapQuery;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));

    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['timezone' => 'UTC']);
    $this->organization->users()->attach($this->user->id, ['role' => OrganizationRole::Owner->value]);
    $this->lab = createLabWithHierarchy($this->organization, 1, 1, 1);
    $this->lab->forceFill([
        'created_at' => CarbonImmutable::parse('2026-10-26 12:00:00', 'UTC'),
        'updated_at' => CarbonImmutable::parse('2026-10-26 12:00:00', 'UTC'),
    ])->saveQuietly();
    $this->sample = createSample($this->lab);
    $this->levelBacteria = Bacteria::factory()->create([
        'name' => 'Level One',
        'short_name' => 'L1',
        'observation_type' => 'level',
    ]);
    $this->countBacteria = Bacteria::factory()->create([
        'name' => 'Count One',
        'short_name' => 'C1',
        'observation_type' => 'count',
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function labMapSummary(Lab $lab, Sample $sample, string $week): array
{
    $map = app(LabMapQuery::class);
    $weeks = $map->weekOptions($lab);
    $selected = $map->selectedWeek($weeks, $week);
    $weekEnd = $map->weekEnd($lab, $selected);
    $summaries = $map->summaries($lab, $weekEnd);

    return $summaries[$sample->id];
}

test('the weekly state carries forward to weeks without observations', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::High);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W45');

    expect($summary['highest_level'])->toBe('high')
        ->and($summary['present_count'])->toBe(1);
});

test('a none observation hides the bacterium from the map', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::High);
    createObservation($this->sample, '2026-11-09 12:00:00', $this->levelBacteria, GrowthLevel::None);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W46');

    expect($summary['present_count'])->toBe(0)
        ->and($summary['highest_level'])->toBeNull();
});

test('a zero count hides the bacterium from the map', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->countBacteria, 1200);
    createObservation($this->sample, '2026-11-09 12:00:00', $this->countBacteria, 0);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W46');

    expect($summary['present_count'])->toBe(0)
        ->and($summary['max_count'])->toBeNull();
});

test('a bacterium can reappear after a none observation', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-23 12:00:00', 'UTC'));

    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::High);
    createObservation($this->sample, '2026-11-09 12:00:00', $this->levelBacteria, GrowthLevel::None);
    createObservation($this->sample, '2026-11-16 12:00:00', $this->levelBacteria, GrowthLevel::Low);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W47');

    expect($summary['highest_level'])->toBe('low')
        ->and($summary['present_count'])->toBe(1);
});

test('the summary uses the highest level and the maximum count', function () {
    $high = Bacteria::factory()->create([
        'name' => 'High One',
        'short_name' => 'H1',
        'observation_type' => 'level',
    ]);

    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::Low);
    createObservation($this->sample, '2026-11-02 12:05:00', $high, GrowthLevel::High);
    createObservation($this->sample, '2026-11-02 12:10:00', $this->countBacteria, 1200);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W45');

    expect($summary['highest_level'])->toBe('high')
        ->and($summary['max_count'])->toBe(1200)
        ->and($summary['present_count'])->toBe(3)
        ->and($summary['level_count'])->toBe(2)
        ->and($summary['count_count'])->toBe(1)
        ->and($summary['single_bacterium'])->toBeNull();
});

test('a single present bacterium is returned for the cell', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::Medium);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W45');

    expect($summary['single_bacterium'])
        ->toMatchArray([
            'id' => $this->levelBacteria->id,
            'name' => 'Level One',
            'growth_level' => 'medium',
            'count' => null,
        ]);
});

test('the lab map page returns the hierarchy and current week', function () {
    $this->actingAs($this->user)
        ->get(route('labs.show', $this->lab))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Show')
            ->has('weeks', 3)
            ->where('selectedWeek.value', '2026-W46')
            ->has('rows', 1)
            ->has('rows.0.shelves', 1)
            ->has('rows.0.shelves.0.levels', 1)
            ->has('rows.0.shelves.0.levels.0.samples', 1)
            ->where('rows.0.shelves.0.levels.0.samples.0.summary.present_count', 0));
});

test('the sample bacteria endpoint returns the selected week state', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::High);

    $this->actingAs($this->user)
        ->getJson(route('labs.samples.bacteria', [
            $this->lab,
            $this->sample,
            'week' => '2026-W45',
        ]))
        ->assertOk()
        ->assertJsonPath('bacteria.0.id', $this->levelBacteria->id)
        ->assertJsonPath('bacteria.0.growth_level', 'high');
});

test('the observation iso week is computed in the organization timezone', function () {
    $organization = Organization::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $sample = createSample($lab);
    $bacteria = Bacteria::factory()->create(['observation_type' => 'level']);

    $observation = createObservation(
        $sample,
        '2025-12-29 01:30:00 UTC',
        $bacteria,
        GrowthLevel::High,
    );

    expect($observation->iso_year)->toBe(2025)
        ->and($observation->iso_week)->toBe(52);
});
