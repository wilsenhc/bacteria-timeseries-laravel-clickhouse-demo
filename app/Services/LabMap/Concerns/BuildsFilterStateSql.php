<?php

namespace App\Services\LabMap\Concerns;

trait BuildsFilterStateSql
{
    /**
     * @param  array<int, int>  $sampleIds
     */
    protected function buildFilterStateSql(
        string $filterTable,
        string $filterObservationTable,
        bool $scopeToLab = false,
        array $sampleIds = [],
    ): string {
        $sampleScope = $sampleIds === []
            ? ''
            : ' AND f.sample_id IN ('.implode(', ', array_fill(0, count($sampleIds), '?')).')';

        return sprintf(<<<'SQL'
SELECT
    f.lab_id,
    f.sample_id,
    f.filter_id,
    if(max(fo.observed_at) = toDateTime64(0, 6, 'UTC'), -1, argMax(fo.value, fo.observed_at)) AS filter_value
FROM %s AS f
LEFT JOIN %s AS fo
    ON fo.filter_id = f.filter_id AND fo.week_start = toDate(?)
WHERE %sf.placed_week <= toDate(?)%s
GROUP BY f.lab_id, f.sample_id, f.filter_id
SQL, $filterTable, $filterObservationTable, $scopeToLab ? 'f.lab_id = ? AND ' : '', $sampleScope);
    }
}
