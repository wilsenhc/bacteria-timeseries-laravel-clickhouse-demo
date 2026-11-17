<?php

use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Organization;
use App\Services\LabMap\ClickHouseLabMapService;
use App\Services\LabMap\ClickHouseSnapshotLabMapService;
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

    if (! Schema::connection('clickhouse')->hasTable(LabWeeklySummary::TABLE)) {
        $this->markTestSkipped('ClickHouse tables are missing. Run php artisan migrate.');
    }

    $this->clickHouseSummaryTable = 'lab_summaries_test_'.Str::lower(Str::random(8));
    DB::connection('clickhouse')->statement('CREATE TABLE '.$this->clickHouseSummaryTable.' AS '.LabWeeklySummary::TABLE);
});

afterEach(function () {
    if (isset($this->clickHouseSummaryTable)) {
        DB::connection('clickhouse')->statement('DROP TABLE IF EXISTS '.$this->clickHouseSummaryTable);
    }
});

it('serves a week from the precomputed summary snapshot', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $mixed = createSample($lab, 'R1-S1-L1-01');
    $single = createSample($lab, 'R1-S1-L1-02');
    $filtered = createSample($lab, 'R1-S1-L1-03');

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

    $service = new ClickHouseSnapshotLabMapService(
        new ClickHouseLabMapService,
        $this->clickHouseSummaryTable,
    );
    $summaries = $service->summaries($lab, CarbonImmutable::parse('2026-11-15 23:59:59', 'UTC'), $service->hierarchy($lab));

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
