<?php

namespace App\Services\LabMap;

use App\Data\LabMap\BacteriaState;
use App\Data\LabMap\SampleFilter;
use App\Enums\LabMapEngine;
use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\Lab;
use App\Models\Sample;
use App\Services\LabMap\Concerns\BuildsFilterStateSql;
use App\Services\LabMap\Concerns\MapsClickHouseRows;
use App\Services\LabMap\Concerns\ProvidesLabMapPageData;
use App\Support\ObservationWeek;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClickHouseLabMapService implements LabMapService
{
    use BuildsFilterStateSql;
    use MapsClickHouseRows;
    use ProvidesLabMapPageData;

    public function __construct(
        private readonly string $table = LabObservation::TABLE,
        private readonly string $filterTable = LabFilter::TABLE,
        private readonly string $filterObservationTable = LabFilterObservation::TABLE,
    ) {}

    public function engine(): LabMapEngine
    {
        return LabMapEngine::ClickHouse;
    }

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    protected function querySummaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $this->ensureLoaded();

        $weekStart = ObservationWeek::for($weekEnd, $lab->organization->timezone)->startDate;

        $rows = DB::connection('clickhouse')->select(
            $this->summarySql($this->statesSql()),
            $this->summaryBindings($lab, $weekStart),
        );

        return $this->mapSummaries([...$rows, ...$this->filterOnlyRows($lab, $weekStart, $rows)]);
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    protected function querySummariesForSamples(Lab $lab, CarbonImmutable $weekEnd, array $sampleIds): array
    {
        if ($sampleIds === []) {
            return [];
        }

        $this->ensureLoaded();

        $weekStart = ObservationWeek::for($weekEnd, $lab->organization->timezone)->startDate;

        $rows = DB::connection('clickhouse')->select(
            $this->summarySql($this->statesSql($sampleIds)),
            $this->summaryBindings($lab, $weekStart, $sampleIds),
        );

        return $this->mapSummaries([...$rows, ...$this->filterOnlyRows($lab, $weekStart, $rows, $sampleIds)]);
    }

    /**
     * @return array<int, BacteriaState>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        $sample->loadMissing('lab.organization');

        $weekStart = ObservationWeek::for($weekEnd, $sample->lab->organization->timezone)->startDate;

        $rows = DB::connection('clickhouse')->select(
            $this->sampleBacteriaSql($this->sampleStatesSql()),
            $this->sampleBindings($sample, $weekStart),
        );

        return $this->mapBacteria($rows);
    }

    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?SampleFilter
    {
        $sample->loadMissing('lab.organization');

        $weekStart = ObservationWeek::for($weekEnd, $sample->lab->organization->timezone)->startDate;

        $rows = DB::connection('clickhouse')->select($this->sampleFilterSql(), [
            $weekStart,
            $sample->lab_id,
            $sample->id,
            $weekStart,
        ]);

        return $this->mapFilter($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $sampleIds
     * @return array<int, array<string, mixed>>
     */
    private function filterOnlyRows(Lab $lab, string $weekStart, array $rows, array $sampleIds = []): array
    {
        $presentSampleIds = [];

        foreach ($rows as $row) {
            $presentSampleIds[(int) $row['sample_id']] = true;
        }

        return array_values(array_filter(
            DB::connection('clickhouse')->select(
                $this->filterOnlySql($sampleIds),
                $this->filterOnlyBindings($lab, $weekStart, $sampleIds),
            ),
            fn (array $row): bool => ! isset($presentSampleIds[(int) $row['sample_id']]),
        ));
    }

    /**
     * @param  array<int, int>  $sampleIds
     */
    private function statesSql(array $sampleIds = []): string
    {
        $sampleScope = $sampleIds === []
            ? ''
            : ' AND sample_id IN ('.implode(', ', array_fill(0, count($sampleIds), '?')).')';

        return sprintf(<<<'SQL'
SELECT
    lab_id,
    sample_id,
    bacteria_id,
    argMax(growth_level, observed_at) AS growth_level,
    argMax(`count`, observed_at) AS `count`,
    argMax(observation_type, observed_at) AS observation_type
FROM %s
PREWHERE lab_id = ?
WHERE week_start <= toDate(?)%s
GROUP BY lab_id, sample_id, bacteria_id
SQL, $this->table, $sampleScope);
    }

    /**
     * @param  array<int, int>  $sampleIds
     */
    private function filterStateSql(array $sampleIds = []): string
    {
        return $this->buildFilterStateSql($this->filterTable, $this->filterObservationTable, true, $sampleIds);
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, mixed>
     */
    private function summaryBindings(Lab $lab, string $weekStart, array $sampleIds = []): array
    {
        return [
            $lab->id,
            $weekStart,
            ...$sampleIds,
            $weekStart,
            $lab->id,
            $weekStart,
            ...$sampleIds,
        ];
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, mixed>
     */
    private function filterOnlyBindings(Lab $lab, string $weekStart, array $sampleIds = []): array
    {
        return [
            $weekStart,
            $lab->id,
            $weekStart,
            ...$sampleIds,
        ];
    }

    private function sampleStatesSql(): string
    {
        return sprintf(<<<'SQL'
SELECT
    bacteria_id,
    argMax(growth_level, observed_at) AS growth_level,
    argMax(`count`, observed_at) AS `count`,
    argMax(bacteria_name, observed_at) AS bacteria_name,
    argMax(bacteria_short_name, observed_at) AS bacteria_short_name,
    argMax(bacteria_color, observed_at) AS bacteria_color,
    argMax(bacteria_icon, observed_at) AS bacteria_icon,
    argMax(observation_type, observed_at) AS observation_type
FROM %s
PREWHERE lab_id = ?
WHERE sample_id = ? AND week_start <= toDate(?)
GROUP BY bacteria_id
SQL, $this->table);
    }

    private function sampleFilterSql(): string
    {
        return sprintf(<<<'SQL'
SELECT
    f.filter_id,
    f.placed_at,
    f.placed_iso_year,
    f.placed_iso_week,
    if(max(fo.observed_at) = toDateTime64(0, 6, 'UTC'), -1, argMax(fo.value, fo.observed_at)) AS filter_value
FROM %s AS f
LEFT JOIN %s AS fo
    ON fo.filter_id = f.filter_id AND fo.week_start = toDate(?)
WHERE f.lab_id = ? AND f.sample_id = ? AND f.placed_week <= toDate(?)
GROUP BY f.filter_id, f.placed_at, f.placed_iso_year, f.placed_iso_week
SQL, $this->filterTable, $this->filterObservationTable);
    }

    /**
     * @return array<int, mixed>
     */
    private function sampleBindings(Sample $sample, string $weekStart): array
    {
        return [$sample->lab_id, $sample->id, $weekStart];
    }

    private function summarySql(string $states): string
    {
        return sprintf(<<<'SQL'
SELECT
    b.*,
    f.filter_id,
    f.filter_value
FROM (
    SELECT
        lab_id,
        sample_id,
        countIf(present) AS present_count,
        countIf(present AND observation_type = 'level') AS level_count,
        countIf(present AND observation_type = 'count') AS count_count,
        maxIf(growth_level, present AND observation_type = 'level') AS highest_level,
        maxIf(`count`, present AND observation_type = 'count') AS max_count,
        anyIf(bacteria_id, present) AS single_id,
        anyIf(observation_type, present) AS single_observation_type,
        anyIf(growth_level, present) AS single_growth_level,
        anyIf(`count`, present) AS single_count
    FROM (
        SELECT
            *,
            if(observation_type = 'count', `count` > 0, growth_level IN ('low', 'medium', 'high')) AS present
        FROM (
%s
        )
    )
    GROUP BY lab_id, sample_id
) AS b
LEFT JOIN (
%s
) AS f ON f.lab_id = b.lab_id AND f.sample_id = b.sample_id
SQL, $states, $this->filterStateSql());
    }

    /**
     * @param  array<int, int>  $sampleIds
     */
    private function filterOnlySql(array $sampleIds = []): string
    {
        return sprintf(<<<'SQL'
SELECT
    f.lab_id,
    f.sample_id,
    0 AS present_count,
    0 AS level_count,
    0 AS count_count,
    'none' AS highest_level,
    -1 AS max_count,
    0 AS single_id,
    '' AS single_observation_type,
    'none' AS single_growth_level,
    -1 AS single_count,
    f.filter_id,
    f.filter_value
FROM (
%s
) AS f
SQL, $this->filterStateSql($sampleIds));
    }

    private function sampleBacteriaSql(string $states): string
    {
        return sprintf(<<<'SQL'
SELECT
    bacteria_id,
    bacteria_name,
    bacteria_short_name,
    bacteria_color,
    bacteria_icon,
    observation_type,
    growth_level,
    `count`
FROM (
%s
)
WHERE if(observation_type = 'count', `count` > 0, growth_level IN ('low', 'medium', 'high'))
ORDER BY bacteria_id
SQL, $states);
    }

    private function ensureLoaded(): void
    {
        if (DB::connection('clickhouse')->selectOne('SELECT 1 FROM '.$this->table.' LIMIT 1') === null) {
            throw new RuntimeException('ClickHouse lab map data is not loaded.');
        }
    }
}
