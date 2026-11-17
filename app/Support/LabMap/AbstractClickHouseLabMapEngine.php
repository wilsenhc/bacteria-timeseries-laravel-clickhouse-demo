<?php

namespace App\Support\LabMap;

use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

abstract class AbstractClickHouseLabMapEngine implements LabMapEngineContract
{
    use MapsClickHouseLabMapRows;

    public function __construct(
        protected readonly string $table,
    ) {
        ClickHouseLabMapTable::assertName($this->table);
    }

    public function summaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $weekStart = $this->weekStart($weekEnd->setTimezone($lab->organization->timezone));

        $rows = DB::connection('clickhouse')->select(
            $this->summarySql($this->statesSql()),
            $this->summaryBindings($lab, $weekStart),
        );

        $presentSampleIds = [];

        foreach ($rows as $row) {
            $presentSampleIds[(int) $row['sample_id']] = true;
        }

        $filterRows = array_values(array_filter(
            DB::connection('clickhouse')->select(
                $this->filterOnlySql(),
                $this->filterOnlyBindings($lab, $weekStart),
            ),
            fn (array $row): bool => ! isset($presentSampleIds[(int) $row['sample_id']]),
        ));

        return $this->mapSummaries([...$rows, ...$filterRows]);
    }

    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        $sample->loadMissing('lab.organization');

        $weekStart = $this->weekStart($weekEnd->setTimezone($sample->lab->organization->timezone));

        $rows = DB::connection('clickhouse')->select(
            $this->sampleBacteriaSql($this->sampleStatesSql()),
            $this->sampleBindings($sample, $weekStart),
        );

        return $this->mapBacteria($rows);
    }

    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?array
    {
        $sample->loadMissing('lab.organization');

        $weekStart = $this->weekStart($weekEnd->setTimezone($sample->lab->organization->timezone));

        $rows = DB::connection('clickhouse')->select($this->sampleFilterSql(), [
            $weekStart,
            $sample->lab_id,
            $sample->id,
            $weekStart,
        ]);

        return $this->mapFilter($rows);
    }

    /**
     * The inner query that produces one row per sample+bacterium with the
     * columns growth_level, count, bacteria_name, bacteria_short_name,
     * bacteria_color, bacteria_icon, and observation_type.
     */
    abstract protected function statesSql(): string;

    /**
     * The filter state for the selected week: one row per placed filter with
     * its latest reading, or -1 when that week has no reading.
     */
    abstract protected function filterStateSql(): string;

    /**
     * @return array<int, mixed>
     */
    abstract protected function summaryBindings(Lab $lab, string $weekStart): array;

    /**
     * @return array<int, mixed>
     */
    abstract protected function filterOnlyBindings(Lab $lab, string $weekStart): array;

    /**
     * @return string The inner query for one sample.
     */
    abstract protected function sampleStatesSql(): string;

    /**
     * @return string The query for one sample's filter placement and week reading.
     */
    abstract protected function sampleFilterSql(): string;

    /**
     * @return array<int, mixed>
     */
    abstract protected function sampleBindings(Sample $sample, string $weekStart): array;

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

    private function filterOnlySql(): string
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
SQL, $this->filterStateSql());
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

    private function weekStart(CarbonImmutable $weekEnd): string
    {
        return $weekEnd
            ->startOfWeek(CarbonInterface::MONDAY)
            ->toDateString();
    }
}
