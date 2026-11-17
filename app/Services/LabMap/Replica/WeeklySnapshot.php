<?php

namespace App\Services\LabMap\Replica;

use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\ClickHouse\LabWeeklySummary;
use App\Services\LabMap\Concerns\BuildsFilterStateSql;

/**
 * The ClickHouse statements that precompute one week of lab-map summaries.
 *
 * Shared by `clickhouse:load` and the equivalence tests, so the snapshot the app reads
 * is always built by the SQL the tests compare against the other engines.
 */
final class WeeklySnapshot
{
    use BuildsFilterStateSql;

    public function __construct(
        private readonly string $summaryTable = LabWeeklySummary::TABLE,
        private readonly string $observationTable = LabObservation::TABLE,
        private readonly string $filterTable = LabFilter::TABLE,
        private readonly string $filterObservationTable = LabFilterObservation::TABLE,
    ) {}

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    public function summariesStatement(string $weekStart): array
    {
        return [$this->summariesSql(), [$weekStart, $weekStart, $weekStart, $weekStart]];
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    public function filterOnlyStatement(string $weekStart): array
    {
        return [$this->filterOnlySql(), [$weekStart, $weekStart, $weekStart, $weekStart]];
    }

    private function summariesSql(): string
    {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    b.lab_id,
    toDate(?) AS week_start,
    b.sample_id,
    b.present_count,
    b.level_count,
    b.count_count,
    b.highest_level,
    b.max_count,
    b.single_id,
    b.single_observation_type,
    b.single_growth_level,
    b.single_count,
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
            lab_id,
            sample_id,
            bacteria_id,
            argMax(growth_level, observed_at) AS growth_level,
            argMax(`count`, observed_at) AS `count`,
            argMax(observation_type, observed_at) AS observation_type,
            if(observation_type = 'count', `count` > 0, growth_level IN ('low', 'medium', 'high')) AS present
        FROM %s
        WHERE week_start <= toDate(?)
        GROUP BY lab_id, sample_id, bacteria_id
    )
    GROUP BY lab_id, sample_id
    HAVING present_count > 0
) AS b
LEFT JOIN (
%s
) AS f ON f.lab_id = b.lab_id AND f.sample_id = b.sample_id
SQL, $this->summaryTable, $this->observationTable, $this->buildFilterStateSql($this->filterTable, $this->filterObservationTable));
    }

    private function filterOnlySql(): string
    {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    f.lab_id,
    toDate(?) AS week_start,
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
LEFT JOIN (
    SELECT lab_id, sample_id
    FROM %s
    WHERE week_start = toDate(?)
    GROUP BY lab_id, sample_id
) AS existing ON existing.lab_id = f.lab_id AND existing.sample_id = f.sample_id
WHERE existing.sample_id = 0
SQL, $this->summaryTable, $this->buildFilterStateSql($this->filterTable, $this->filterObservationTable), $this->summaryTable);
    }
}
