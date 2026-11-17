<?php

use App\Enums\GrowthLevel;
use App\Models\Bacteria;
use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Filter;
use App\Models\FilterObservation;
use App\Models\Organization;
use App\Services\LabMap\ClickHouseLabMapService;
use App\Services\LabMap\ClickHouseSnapshotLabMapService;
use App\Services\LabMap\PostgresLabMapService;
use App\Services\LabMap\Replica\WeeklySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

beforeEach(function () {
    try {
        DB::connection('clickhouse')->select('SELECT 1');
    } catch (Throwable $exception) {
        $this->markTestSkipped('ClickHouse is not reachable: '.$exception->getMessage());
    }

    foreach ([LabObservation::TABLE, LabFilter::TABLE, LabFilterObservation::TABLE, LabWeeklySummary::TABLE] as $table) {
        if (! Schema::connection('clickhouse')->hasTable($table)) {
            $this->markTestSkipped('ClickHouse tables are missing. Run php artisan migrate.');
        }
    }

    $suffix = Str::lower(Str::random(8));

    $this->clickHouseTable = 'lab_observations_test_'.$suffix;
    $this->clickHouseFilterTable = 'lab_filters_test_'.$suffix;
    $this->clickHouseFilterObservationTable = 'lab_filter_observations_test_'.$suffix;
    $this->clickHouseSummaryTable = 'lab_summaries_test_'.$suffix;

    $clickhouse = DB::connection('clickhouse');
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseTable.' AS '.LabObservation::TABLE);
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseFilterTable.' AS '.LabFilter::TABLE);
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseFilterObservationTable.' AS '.LabFilterObservation::TABLE);
    $clickhouse->statement('CREATE TABLE '.$this->clickHouseSummaryTable.' AS '.LabWeeklySummary::TABLE);
});

afterEach(function () {
    foreach ([
        $this->clickHouseSummaryTable,
        $this->clickHouseFilterObservationTable,
        $this->clickHouseFilterTable,
        $this->clickHouseTable,
    ] as $table) {
        DB::connection('clickhouse')->statement('DROP TABLE IF EXISTS '.$table);
    }
});

it('returns the same week from postgres, raw clickhouse, and the snapshot build', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $withBacteria = createSample($lab, 'R1-S1-L1-01');
    $mixed = createSample($lab, 'R1-S1-L1-02');
    $countOnly = createSample($lab, 'R1-S1-L1-03');

    $level = Bacteria::factory()->create([
        'name' => 'Level One',
        'short_name' => 'L1',
        'observation_type' => 'level',
    ]);
    $count = Bacteria::factory()->create([
        'name' => 'Count One',
        'short_name' => 'C1',
        'observation_type' => 'count',
    ]);

    createObservation($withBacteria, '2026-11-02 12:00:00', $level, GrowthLevel::High);
    createObservation($withBacteria, '2026-11-09 12:00:00', $level, GrowthLevel::None);
    createObservation($mixed, '2026-11-09 12:00:00', $level, GrowthLevel::Low);
    createObservation($mixed, '2026-11-09 12:00:01', $count, 1200);
    createObservation($countOnly, '2026-11-09 12:00:02', $count, 0);

    $placedFilter = Filter::factory()->forSample($withBacteria)->create(['placed_at' => '2026-11-02 12:00:00']);
    FilterObservation::create([
        'filter_id' => $placedFilter->id,
        'observed_at' => '2026-11-10 10:00:00',
        'week_start' => '2026-11-09',
        'value' => 11,
    ]);
    $filterOnlyFilter = Filter::factory()->forSample($countOnly)->create(['placed_at' => '2026-11-02 12:00:00']);
    FilterObservation::create([
        'filter_id' => $filterOnlyFilter->id,
        'observed_at' => '2026-11-10 10:00:00',
        'week_start' => '2026-11-09',
        'value' => 22,
    ]);

    insertClickHouseStates($this->clickHouseTable, $lab, [
        ['sample' => $withBacteria, 'bacteria' => $level->id, 'observed_at' => '2026-11-02 12:00:00', 'iso_week' => 45, 'week_start' => '2026-11-02', 'growth_level' => 'high', 'count' => -1, 'name' => $level->name, 'short_name' => $level->short_name, 'observation_type' => 'level'],
        ['sample' => $withBacteria, 'bacteria' => $level->id, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'none', 'count' => -1, 'name' => $level->name, 'short_name' => $level->short_name, 'observation_type' => 'level'],
        ['sample' => $mixed, 'bacteria' => $level->id, 'observed_at' => '2026-11-09 12:00:00', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'low', 'count' => -1, 'name' => $level->name, 'short_name' => $level->short_name, 'observation_type' => 'level'],
        ['sample' => $mixed, 'bacteria' => $count->id, 'observed_at' => '2026-11-09 12:00:01', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'not_applicable', 'count' => 1200, 'name' => $count->name, 'short_name' => $count->short_name, 'observation_type' => 'count'],
        ['sample' => $countOnly, 'bacteria' => $count->id, 'observed_at' => '2026-11-09 12:00:02', 'iso_week' => 46, 'week_start' => '2026-11-09', 'growth_level' => 'not_applicable', 'count' => 0, 'name' => $count->name, 'short_name' => $count->short_name, 'observation_type' => 'count'],
    ]);

    insertClickHouseFilters($this->clickHouseFilterTable, $this->clickHouseFilterObservationTable, $placedFilter, [
        ['observed_at' => '2026-11-10 10:00:00', 'week_start' => '2026-11-09', 'value' => 11],
    ]);
    insertClickHouseFilters($this->clickHouseFilterTable, $this->clickHouseFilterObservationTable, $filterOnlyFilter, [
        ['observed_at' => '2026-11-10 10:00:00', 'week_start' => '2026-11-09', 'value' => 22],
    ]);

    $weekEnd = CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC');

    $snapshot = new WeeklySnapshot(
        $this->clickHouseSummaryTable,
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    );

    [$sql, $bindings] = $snapshot->summariesStatement('2026-11-09');
    DB::connection('clickhouse')->statement($sql, $bindings);

    [$sql, $bindings] = $snapshot->filterOnlyStatement('2026-11-09');
    DB::connection('clickhouse')->statement($sql, $bindings);

    $raw = new ClickHouseLabMapService(
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    );
    $snapshot = new ClickHouseSnapshotLabMapService($raw, $this->clickHouseSummaryTable);
    $hierarchy = $raw->hierarchy($lab);

    $postgres = app(PostgresLabMapService::class)->summaries($lab, $weekEnd, $hierarchy);
    $rawSummaries = $raw->summaries($lab, $weekEnd, $hierarchy);
    $precomputed = $snapshot->summaries($lab, $weekEnd, $hierarchy);
    $subset = $raw->summariesForSamples($lab, $weekEnd, [$withBacteria->id, $mixed->id, $countOnly->id]);

    expect($postgres)->toHaveKeys([$withBacteria->id, $mixed->id, $countOnly->id])
        ->and($rawSummaries)->toEqual($postgres)
        ->and($precomputed)->toEqual($postgres)
        ->and($subset)->toEqual($postgres);
});

it('reports a not-loaded replica instead of an empty map', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $weekEnd = CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC');

    $raw = new ClickHouseLabMapService(
        $this->clickHouseTable,
        $this->clickHouseFilterTable,
        $this->clickHouseFilterObservationTable,
    );
    $snapshot = new ClickHouseSnapshotLabMapService($raw, $this->clickHouseSummaryTable);
    $hierarchy = $raw->hierarchy($lab);

    expect(fn () => $raw->summaries($lab, $weekEnd, $hierarchy))->toThrow(RuntimeException::class)
        ->and(fn () => $snapshot->summaries($lab, $weekEnd, $hierarchy))->toThrow(RuntimeException::class);
});
