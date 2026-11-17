<?php

namespace App\Support\LabMap;

use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ClickHouseSnapshotLabMapEngine implements LabMapEngineContract
{
    use MapsClickHouseLabMapRows;

    public function __construct(
        private readonly string $table = ClickHouseLabMapTable::SUMMARY_NAME,
        private readonly ?LabMapEngineContract $drawerEngine = null,
    ) {
        ClickHouseLabMapTable::assertName($this->table);
    }

    public function summaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $weekStart = $weekEnd
            ->setTimezone($lab->organization->timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->toDateString();

        $rows = DB::connection('clickhouse')->select(sprintf(<<<'SQL'
SELECT
    sample_id,
    present_count,
    level_count,
    count_count,
    highest_level,
    max_count,
    single_id,
    single_observation_type,
    single_growth_level,
    single_count,
    filter_id,
    filter_value
FROM %s
PREWHERE lab_id = ?
WHERE week_start = toDate(?)
SQL, $this->table), [$lab->id, $weekStart]);

        return $this->mapSummaries($rows);
    }

    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        return ($this->drawerEngine ?? app(ClickHouseLabMapEngine::class))
            ->sampleBacteria($sample, $weekEnd);
    }

    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?array
    {
        return ($this->drawerEngine ?? app(ClickHouseLabMapEngine::class))
            ->sampleFilter($sample, $weekEnd);
    }
}
