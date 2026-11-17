<?php

use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\Filter;
use App\Models\Organization;
use App\Services\LabMap\ClickHouseLabMapService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

beforeEach(function () {
    try {
        DB::connection('clickhouse')->select('SELECT 1');
    } catch (Throwable $exception) {
        $this->markTestSkipped('ClickHouse is not reachable: '.$exception->getMessage());
    }

    if (! Schema::connection('clickhouse')->hasTable(LabObservation::TABLE)) {
        $this->markTestSkipped('ClickHouse tables are missing. Run php artisan migrate.');
    }

    $this->clickHouseTable = 'lab_observations_test_'.Str::lower(Str::random(8));
    $this->clickHouseFilterTable = 'lab_filters_test_'.Str::lower(Str::random(8));
    $this->clickHouseFilterObservationTable = 'lab_filter_observations_test_'.Str::lower(Str::random(8));

    $clickhouse = DB::connection('clickhouse');
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseTable.' AS '.LabObservation::TABLE);
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseFilterTable.' AS '.LabFilter::TABLE);
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseFilterObservationTable.' AS '.LabFilterObservation::TABLE);
});

afterEach(function () {
    foreach ([$this->clickHouseFilterObservationTable, $this->clickHouseFilterTable, $this->clickHouseTable] as $table) {
        if (isset($table)) {
            DB::connection('clickhouse')->statement('DROP TABLE IF EXISTS '.$table);
        }
    }
});

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

    $engine = new ClickHouseLabMapService($this->clickHouseTable, $this->clickHouseFilterTable, $this->clickHouseFilterObservationTable);
    $summaries = $engine->summaries($lab, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'), $engine->hierarchy($lab));

    expect($summaries)
        ->toHaveKey($absent->id)
        ->toHaveKey($mixed->id)
        ->toHaveKey($single->id)
        ->and($summaries[$absent->id])
        ->toMatchArray([
            'present_count' => 0,
            'highest_level' => null,
        ])
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

    $bacteria = (new ClickHouseLabMapService($this->clickHouseTable, $this->clickHouseFilterTable, $this->clickHouseFilterObservationTable))
        ->sampleBacteria($sample, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));

    expect($bacteria)->toHaveCount(1)
        ->and($bacteria[0]->toArray())
        ->toMatchArray([
            'id' => 11,
            'name' => 'Count One',
            'observation_type' => 'count',
            'growth_level' => null,
            'count' => 1200,
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

    $engine = new ClickHouseLabMapService(
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    );
    $summaries = $engine->summaries($lab, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'), $engine->hierarchy($lab));

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

    $engine = new ClickHouseLabMapService(
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    );

    $beforePlacement = $engine->sampleFilter($sample, CarbonImmutable::parse('2026-11-01 23:59:59', 'UTC'));
    $selectedWeek = $engine->sampleFilter($sample, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'));
    $noReading = $engine->sampleFilter($sample, CarbonImmutable::parse('2026-11-22 23:59:59', 'UTC'));

    expect($beforePlacement)->toBeNull()
        ->and($selectedWeek?->toArray())->toMatchArray([
            'placed_week' => '2026-W45',
            'value' => 7,
        ])
        ->and($noReading?->toArray())->toMatchArray([
            'placed_week' => '2026-W45',
            'value' => null,
        ]);
});
