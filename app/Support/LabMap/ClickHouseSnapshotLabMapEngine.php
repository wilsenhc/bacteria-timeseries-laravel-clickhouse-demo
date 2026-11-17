<?php

namespace App\Support\LabMap;

use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ClickHouseSnapshotLabMapEngine implements LabMapEngineContract
{
    use MapsClickHouseLabMapRows;

    public function __construct(
        private readonly string $table = LabWeeklySummary::TABLE,
        private readonly ?LabMapEngineContract $drawerEngine = null,
    ) {}

    public function summaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $weekStart = $weekEnd
            ->setTimezone($lab->organization->timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->toDateString();

        $query = (new LabWeeklySummary)
            ->setTable($this->table)
            ->newQuery();

        $rows = $query
            ->toBase()
            ->select([
                'sample_id',
                'present_count',
                'level_count',
                'count_count',
                'highest_level',
                'max_count',
                'single_id',
                'single_observation_type',
                'single_growth_level',
                'single_count',
                'filter_id',
                'filter_value',
            ])
            ->where('lab_id', $lab->id)
            ->where('week_start', $weekStart)
            ->get()
            ->map(fn (mixed $summary): array => (array) $summary)
            ->all();

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
