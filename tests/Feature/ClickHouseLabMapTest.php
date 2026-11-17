<?php

use App\Models\Filter;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\Sample;
use App\Support\LabMap\ClickHouseLabMapEngine;
use App\Support\LabMap\ClickHouseLabMapTable;
use App\Support\LabMap\ClickHouseSnapshotLabMapEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

beforeEach(function () {
    try {
        DB::connection('clickhouse')->select('SELECT 1');
    } catch (Throwable $exception) {
        $this->markTestSkipped('ClickHouse is not reachable: '.$exception->getMessage());
    }

    $this->clickHouseTable = 'lab_observations_test_'.Str::lower(Str::random(8));
    $this->clickHouseFilterTable = 'lab_filters_test_'.Str::lower(Str::random(8));
    $this->clickHouseFilterObservationTable = 'lab_filter_observations_test_'.Str::lower(Str::random(8));
    $this->clickHouseSummaryTable = null;
    ClickHouseLabMapTable::create(DB::connection('clickhouse'), $this->clickHouseTable);
    ClickHouseLabMapTable::createFilters(DB::connection('clickhouse'), $this->clickHouseFilterTable);
    ClickHouseLabMapTable::createFilterObservations(DB::connection('clickhouse'), $this->clickHouseFilterObservationTable);
});

afterEach(function () {
    if (isset($this->clickHouseSummaryTable)) {
        ClickHouseLabMapTable::dropSummary(DB::connection('clickhouse'), $this->clickHouseSummaryTable);
    }

    if (isset($this->clickHouseFilterObservationTable)) {
        ClickHouseLabMapTable::dropFilterObservations(DB::connection('clickhouse'), $this->clickHouseFilterObservationTable);
    }

    if (isset($this->clickHouseFilterTable)) {
        ClickHouseLabMapTable::dropFilters(DB::connection('clickhouse'), $this->clickHouseFilterTable);
    }

    if (isset($this->clickHouseTable)) {
        ClickHouseLabMapTable::drop(DB::connection('clickhouse'), $this->clickHouseTable);
    }
});

/**
 * @param  array<int, array{sample: Sample, bacteria: int, observed_at: string, iso_week: int, week_start: string, growth_level: string, count: int, name: string, short_name: string, observation_type: string}>  $states
 */
function insertClickHouseStates(string $table, Lab $lab, array $states): void
{
    $values = array_map(fn (array $state): string => sprintf(
        "(%d, %d, %d, '%s', 2026, %d, '%s', '%s', %d, '%s', '%s', '#123456', 'rod', '%s')",
        $lab->id,
        $state['sample']->id,
        $state['bacteria'],
        $state['observed_at'],
        $state['iso_week'],
        $state['week_start'],
        $state['growth_level'],
        $state['count'],
        $state['name'],
        $state['short_name'],
        $state['observation_type'],
    ), $states);

    DB::connection('clickhouse')->statement(sprintf(
        'INSERT INTO %s (lab_id, sample_id, bacteria_id, observed_at, iso_year, iso_week, week_start, growth_level, `count`, bacteria_name, bacteria_short_name, bacteria_color, bacteria_icon, observation_type) VALUES %s',
        $table,
        implode(', ', $values),
    ));
}

/**
 * @param  array<int, array{observed_at: string, week_start: string, value: int}>  $readings
 */
function insertClickHouseFilters(
    string $filterTable,
    string $filterObservationTable,
    Filter $filter,
    array $readings = [],
): void {
    DB::connection('clickhouse')->statement(sprintf(
        "INSERT INTO %s (filter_id, lab_id, sample_id, placed_at, placed_week, placed_iso_year, placed_iso_week) VALUES (%d, %d, %d, '%s', '%s', %d, %d)",
        $filterTable,
        $filter->id,
        $filter->lab_id,
        $filter->sample_id,
        $filter->placed_at->utc()->toDateTimeString(),
        $filter->week_start,
        $filter->iso_year,
        $filter->iso_week,
    ));

    if ($readings === []) {
        return;
    }

    $values = array_map(fn (array $reading): string => sprintf(
        "(%d, %d, %d, '%s', '%s', %d)",
        $filter->id,
        $filter->lab_id,
        $filter->sample_id,
        $reading['observed_at'],
        $reading['week_start'],
        $reading['value'],
    ), $readings);

    DB::connection('clickhouse')->statement(sprintf(
        'INSERT INTO %s (filter_id, lab_id, sample_id, observed_at, week_start, value) VALUES %s',
        $filterObservationTable,
        implode(', ', $values),
    ));
}

it('computes the weekly state summary from clickhouse', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $absent = createSample($lab, 'R1-S1-L1-01');
    $mixed = createSample($lab, 'R1-S1-L1-02');
    $single = createSample($lab, 'R1-S1-L1-03');

    insertClickHouseStates($this->clickHouseTable, $lab, [
        ['sample' => $absent, 'bacteria' => 10, 'observed_at' => '2026-11-02 12:00:00', 'iso_week' => 45, 'week_start' => '2026-11-02', 'growth_level' => 'high', 'count' => -1, 'name' => 'Level One', 'short_name' => 'L1', 'observation_type' => 'level'],
        ['sample' => $absent, 'bacteria' => 10, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'none', 'count' => -1, 'name' => 'Level One', 'short_name' => 'L1', 'observation_type' => 'level'],
        ['sample' => $mixed, 'bacteria' => 10, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'low', 'count' => -1, 'name' => 'Level One', 'short_name' => 'L1', 'observation_type' => 'level'],
        ['sample' => $mixed, 'bacteria' => 11, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'not_applicable', 'count' => 1200, 'name' => 'Count One', 'short_name' => 'C1', 'observation_type' => 'count'],
        ['sample' => $single, 'bacteria' => 12, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'high', 'count' => -1, 'name' => 'Single One', 'short_name' => 'S1', 'observation_type' => 'level'],
    ]);

    $summaries = (new ClickHouseLabMapEngine($this->clickHouseTable, $this->clickHouseFilterTable, $this->clickHouseFilterObservationTable))
        ->summaries($lab, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));

    expect($summaries)
        ->not->toHaveKey($absent->id)
        ->toHaveKey($mixed->id)
        ->toHaveKey($single->id)
        ->and($summaries[$mixed->id])
        ->toMatchArray([
            'present_count' => 2,
            'level_count' => 1,
            'count_count' => 1,
            'highest_level' => 'low',
            'max_count' => 1200,
            'single' => null,
        ])
        ->and($summaries[$single->id]['single'])
        ->toMatchArray([
            'id' => 12,
            'observation_type' => 'level',
            'growth_level' => 'high',
            'count' => null,
        ]);
});

it('returns the present bacteria for one sample from clickhouse', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $sample = createSample($lab);

    insertClickHouseStates($this->clickHouseTable, $lab, [
        ['sample' => $sample, 'bacteria' => 10, 'observed_at' => '2026-11-02 12:00:00', 'iso_week' => 45, 'week_start' => '2026-11-02', 'growth_level' => 'high', 'count' => -1, 'name' => 'Level One', 'short_name' => 'L1', 'observation_type' => 'level'],
        ['sample' => $sample, 'bacteria' => 10, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'none', 'count' => -1, 'name' => 'Level One', 'short_name' => 'L1', 'observation_type' => 'level'],
        ['sample' => $sample, 'bacteria' => 11, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'not_applicable', 'count' => 1200, 'name' => 'Count One', 'short_name' => 'C1', 'observation_type' => 'count'],
    ]);

    $bacteria = (new ClickHouseLabMapEngine($this->clickHouseTable, $this->clickHouseFilterTable, $this->clickHouseFilterObservationTable))
        ->sampleBacteria($sample, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));

    expect($bacteria)->toHaveCount(1)
        ->and($bacteria[0])
        ->toMatchArray([
            'id' => 11,
            'name' => 'Count One',
            'observation_type' => 'count',
            'growth_level' => null,
            'count' => 1200,
        ]);
});

it('serves a week from the precomputed summary snapshot', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $mixed = createSample($lab, 'R1-S1-L1-01');
    $single = createSample($lab, 'R1-S1-L1-02');
    $filtered = createSample($lab, 'R1-S1-L1-03');

    $this->clickHouseSummaryTable = 'lab_summaries_test_'.Str::lower(Str::random(8));
    ClickHouseLabMapTable::createSummary(DB::connection('clickhouse'), $this->clickHouseSummaryTable);

    DB::connection('clickhouse')->statement(sprintf(
        "INSERT INTO %s (lab_id, week_start, sample_id, present_count, level_count, count_count, highest_level, max_count, single_id, single_observation_type, single_growth_level, single_count, filter_id, filter_value) VALUES (%d, '2026-11-09', %d, 2, 1, 1, 'low', 1200, 0, '', 'none', -1, 0, -1), (%d, '2026-11-09', %d, 1, 1, 0, 'high', -1, 12, 'level', 'high', -1, 0, -1), (%d, '2026-11-09', %d, 0, 0, 0, 'none', -1, 0, '', 'none', -1, 500, 33)",
        $this->clickHouseSummaryTable,
        $lab->id,
        $mixed->id,
        $lab->id,
        $single->id,
        $lab->id,
        $filtered->id,
    ));

    $summaries = (new ClickHouseSnapshotLabMapEngine($this->clickHouseSummaryTable))
        ->summaries($lab, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));

    expect($summaries)->toHaveCount(3)
        ->and($summaries[$mixed->id])
        ->toMatchArray([
            'present_count' => 2,
            'level_count' => 1,
            'count_count' => 1,
            'highest_level' => 'low',
            'max_count' => 1200,
            'single' => null,
        ])
        ->and($summaries[$single->id]['single'])
        ->toMatchArray([
            'id' => 12,
            'observation_type' => 'level',
            'growth_level' => 'high',
            'count' => null,
        ])
        ->and($summaries[$filtered->id])
        ->toMatchArray([
            'present_count' => 0,
            'filter' => ['value' => 33],
        ]);
});

it('adds filter values and filter-only samples to the clickhouse summary', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $withBacteria = createSample($lab, 'R1-S1-L1-01');
    $filterOnly = createSample($lab, 'R1-S1-L1-02');

    insertClickHouseStates($this->clickHouseTable, $lab, [
        ['sample' => $withBacteria, 'bacteria' => 10, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'high', 'count' => -1, 'name' => 'Level One', 'short_name' => 'L1', 'observation_type' => 'level'],
    ]);

    $filterA = Filter::factory()->forSample($withBacteria)->create(['placed_at' => '2026-11-02 12:00:00']);
    $filterB = Filter::factory()->forSample($filterOnly)->create(['placed_at' => '2026-11-02 12:00:00']);

    insertClickHouseFilters($this->clickHouseFilterTable, $this->clickHouseFilterObservationTable, $filterA, [
        ['observed_at' => '2026-11-10 10:00:00', 'week_start' => '2026-11-09', 'value' => 11],
    ]);
    insertClickHouseFilters($this->clickHouseFilterTable, $this->clickHouseFilterObservationTable, $filterB, [
        ['observed_at' => '2026-11-10 10:00:00', 'week_start' => '2026-11-09', 'value' => 22],
    ]);

    $summaries = (new ClickHouseLabMapEngine(
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    ))->summaries($lab, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));

    expect($summaries[$withBacteria->id])
        ->toMatchArray([
            'present_count' => 1,
            'filter' => ['value' => 11],
        ])
        ->and($summaries[$filterOnly->id])
        ->toMatchArray([
            'present_count' => 0,
            'filter' => ['value' => 22],
        ]);
});

it('returns a filter placement and the selected week reading from clickhouse', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $sample = createSample($lab);
    $filter = Filter::factory()->forSample($sample)->create(['placed_at' => '2026-11-02 12:00:00']);

    insertClickHouseFilters($this->clickHouseFilterTable, $this->clickHouseFilterObservationTable, $filter, [
        ['observed_at' => '2026-11-10 10:00:00', 'week_start' => '2026-11-09', 'value' => 42],
        ['observed_at' => '2026-11-11 10:00:00', 'week_start' => '2026-11-09', 'value' => 7],
    ]);

    $engine = new ClickHouseLabMapEngine(
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    );

    $beforePlacement = $engine->sampleFilter($sample, CarbonImmutable::parse('2026-11-01 23:59:59', 'UTC'));
    $selectedWeek = $engine->sampleFilter($sample, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));
    $noReading = $engine->sampleFilter($sample, CarbonImmutable::parse('2026-11-22 23:59:59', 'UTC'));

    expect($beforePlacement)->toBeNull()
        ->and($selectedWeek)->toMatchArray([
            'placed_week' => '2026-W45',
            'value' => 7,
        ])
        ->and($noReading)->toMatchArray([
            'placed_week' => '2026-W45',
            'value' => null,
        ]);
});
