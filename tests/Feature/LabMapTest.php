<?php

use App\Actions\BuildLabMapPage;
use App\Enums\GrowthLevel;
use App\Enums\OrganizationRole;
use App\Models\Bacteria;
use App\Models\Filter;
use App\Models\FilterObservation;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\Sample;
use App\Models\User;
use App\Services\LabMap\LabMapService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
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
    $map = app(LabMapService::class);
    $weeks = $map->weekOptions($lab);
    $selected = $map->selectedWeek($weeks, $week);
    $weekEnd = $map->weekEnd($lab, $selected);
    $summaries = $map->summaries($lab, $weekEnd, $map->hierarchy($lab));

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
        ->and($summary['single'])->toBeNull();
});

test('a single present bacterium is returned for the cell', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::Medium);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W45');

    expect($summary['single'])
        ->toMatchArray([
            'id' => $this->levelBacteria->id,
            'observation_type' => 'level',
            'growth_level' => 'medium',
            'count' => null,
        ]);
});

test('a filter appears from its placement week and does not carry over', function () {
    $filter = Filter::factory()->forSample($this->sample)->create([
        'placed_at' => '2026-11-02 12:00:00',
    ]);

    FilterObservation::factory()->for($filter)->create([
        'observed_at' => '2026-11-04 10:00:00',
        'value' => 10,
    ]);
    FilterObservation::factory()->for($filter)->create([
        'observed_at' => '2026-11-05 10:00:00',
        'value' => 15,
    ]);
    FilterObservation::factory()->for($filter)->create([
        'observed_at' => '2026-11-10 10:00:00',
        'value' => 20,
    ]);

    $before = labMapSummary($this->lab, $this->sample, '2026-W44');
    $placed = labMapSummary($this->lab, $this->sample, '2026-W45');
    $next = labMapSummary($this->lab, $this->sample, '2026-W46');

    $week47 = CarbonImmutable::parse('2026-11-22 23:59:59', 'UTC');
    $map = app(LabMapService::class);
    $after = $map->summaries($this->lab, $week47, $map->hierarchy($this->lab));

    expect($before['filter'] ?? null)->toBeNull()
        ->and($placed['present_count'])->toBe(0)
        ->and($placed['filter'])->toBe(['value' => 15])
        ->and($next['filter'])->toBe(['value' => 20])
        ->and($after[$this->sample->id]['filter'])->toBe(['value' => null]);
});

test('a filter does not change the bacteria summary', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::High);

    $filter = Filter::factory()->forSample($this->sample)->create([
        'placed_at' => '2026-11-02 12:00:00',
    ]);
    FilterObservation::factory()->for($filter)->create([
        'observed_at' => '2026-11-04 10:00:00',
        'value' => 40,
    ]);

    $summary = labMapSummary($this->lab, $this->sample, '2026-W45');

    expect($summary)
        ->toMatchArray([
            'present_count' => 1,
            'level_count' => 1,
            'count_count' => 0,
            'highest_level' => 'high',
            'filter' => ['value' => 40],
        ]);
});

test('the sample details partial reload returns the filter placement and reading', function () {
    $filter = Filter::factory()->forSample($this->sample)->create([
        'placed_at' => '2026-11-02 12:00:00',
    ]);
    FilterObservation::factory()->for($filter)->create([
        'observed_at' => '2026-11-04 10:00:00',
        'value' => 42,
    ]);

    $this->actingAs($this->user)
        ->get(route('labs.show', [
            'lab' => $this->lab,
            'week' => '2026-W45',
            'sample' => $this->sample->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Show')
            ->reloadOnly('sampleDetails', fn (Assert $reload) => $reload
                ->where('sampleDetails.filter.placedWeek', '2026-W45')
                ->where('sampleDetails.filter.value', 42)));

    $this->actingAs($this->user)
        ->get(route('labs.show', [
            'lab' => $this->lab,
            'week' => '2026-W44',
            'sample' => $this->sample->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Show')
            ->reloadOnly('sampleDetails', fn (Assert $reload) => $reload
                ->where('sampleDetails.filter', null)));
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
            ->missing('bacteria')
            ->where('rows.0.shelves.0.levels.0.samples.0.id', $this->sample->id)
            ->where('rows.0.shelves.0.levels.0.samples.0.summary.present_count', 0)
            ->where('timings.pgsql.samples', 1));
});

test('the sample details partial reload returns the selected week state', function () {
    createObservation($this->sample, '2026-11-02 12:00:00', $this->levelBacteria, GrowthLevel::High);

    $this->actingAs($this->user)
        ->get(route('labs.show', [
            'lab' => $this->lab,
            'week' => '2026-W45',
            'sample' => $this->sample->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('labs/Show')
            ->reloadOnly('sampleDetails', fn (Assert $reload) => $reload
                ->where('sampleDetails.bacteria.0.id', $this->levelBacteria->id)
                ->where('sampleDetails.bacteria.0.growthLevel', 'high')));
});

test('the sample details action rejects a sample from another lab', function () {
    $otherLab = createLabWithHierarchy($this->organization, 1, 1, 1);
    $otherSample = createSample($otherLab, 'R1-S1-L1-02');
    $request = Request::create(route('labs.show', [
        'lab' => $this->lab,
        'sample' => $otherSample->id,
    ]));

    $this->expectException(ModelNotFoundException::class);

    app(BuildLabMapPage::class)->sampleDetails($request, $this->lab);
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
