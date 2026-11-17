<?php

namespace App\Support\LabMap;

use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\Lab;
use App\Models\Sample;

class ClickHouseLabMapEngine extends AbstractClickHouseLabMapEngine
{
    public function __construct(
        string $table = LabObservation::TABLE,
        private readonly string $filterTable = LabFilter::TABLE,
        private readonly string $filterObservationTable = LabFilterObservation::TABLE,
    ) {
        parent::__construct($table);

    }

    protected function statesSql(): string
    {
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
WHERE week_start <= toDate(?)
GROUP BY lab_id, sample_id, bacteria_id
SQL, $this->table);
    }

    protected function filterStateSql(): string
    {
        return sprintf(<<<'SQL'
SELECT
    f.lab_id,
    f.sample_id,
    f.filter_id,
    if(max(fo.observed_at) = toDateTime64(0, 6, 'UTC'), -1, argMax(fo.value, fo.observed_at)) AS filter_value
FROM %s AS f
LEFT JOIN %s AS fo
    ON fo.filter_id = f.filter_id AND fo.week_start = toDate(?)
WHERE f.lab_id = ? AND f.placed_week <= toDate(?)
GROUP BY f.lab_id, f.sample_id, f.filter_id
SQL, $this->filterTable, $this->filterObservationTable);
    }

    protected function summaryBindings(Lab $lab, string $weekStart): array
    {
        return [
            $lab->id,
            $weekStart,
            $weekStart,
            $lab->id,
            $weekStart,
        ];
    }

    protected function filterOnlyBindings(Lab $lab, string $weekStart): array
    {
        return [
            $weekStart,
            $lab->id,
            $weekStart,
        ];
    }

    protected function sampleStatesSql(): string
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

    protected function sampleFilterSql(): string
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

    protected function sampleBindings(Sample $sample, string $weekStart): array
    {
        return [$sample->lab_id, $sample->id, $weekStart];
    }
}
