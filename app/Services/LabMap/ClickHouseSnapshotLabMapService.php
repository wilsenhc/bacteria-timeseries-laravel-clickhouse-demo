<?php

namespace App\Services\LabMap;

use App\Data\LabMap\BacteriaState;
use App\Data\LabMap\SampleFilter;
use App\Enums\LabMapEngine;
use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Lab;
use App\Models\Sample;
use App\Services\LabMap\Concerns\MapsClickHouseRows;
use App\Services\LabMap\Concerns\ProvidesLabMapPageData;
use App\Support\ObservationWeek;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use RuntimeException;

class ClickHouseSnapshotLabMapService implements LabMapService
{
    use MapsClickHouseRows;
    use ProvidesLabMapPageData;

    public function __construct(
        private readonly ClickHouseLabMapService $raw,
        private readonly string $table = LabWeeklySummary::TABLE,
    ) {}

    public function engine(): LabMapEngine
    {
        return LabMapEngine::ClickHouseSnapshot;
    }

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    protected function querySummaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $this->ensureLoaded();

        $weekStart = ObservationWeek::for($weekEnd, $lab->organization->timezone)->startDate;

        $rows = $this->query()
            ->where('lab_id', $lab->id)
            ->where('week_start', $weekStart)
            ->get()
            ->map(fn (mixed $summary): array => (array) $summary)
            ->all();

        return $this->mapSummaries($rows);
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

        $rows = $this->query()
            ->where('lab_id', $lab->id)
            ->where('week_start', $weekStart)
            ->whereIn('sample_id', $sampleIds)
            ->get()
            ->map(fn (mixed $summary): array => (array) $summary)
            ->all();

        return $this->mapSummaries($rows);
    }

    /**
     * @return array<int, BacteriaState>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        return $this->raw->sampleBacteria($sample, $weekEnd);
    }

    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?SampleFilter
    {
        return $this->raw->sampleFilter($sample, $weekEnd);
    }

    private function query(): Builder
    {
        return (new LabWeeklySummary)
            ->setTable($this->table)
            ->newQuery()
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
            ]);
    }

    private function ensureLoaded(): void
    {
        $query = (new LabWeeklySummary)
            ->setTable($this->table)
            ->newQuery();

        if (! $query->limit(1)->exists()) {
            throw new RuntimeException('ClickHouse lab map data is not loaded.');
        }
    }
}
