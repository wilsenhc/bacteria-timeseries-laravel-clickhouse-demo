<?php

namespace App\Services\LabMap;

use App\Data\LabMap\BacteriaState;
use App\Data\LabMap\SampleFilter;
use App\Enums\BacteriaObservationType;
use App\Enums\GrowthLevel;
use App\Enums\LabMapEngine;
use App\Models\Lab;
use App\Models\Sample;
use App\Services\LabMap\Concerns\ProvidesLabMapPageData;
use App\Support\ObservationWeek;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostgresLabMapService implements LabMapService
{
    use ProvidesLabMapPageData;

    public function engine(): LabMapEngine
    {
        return LabMapEngine::Postgres;
    }

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    protected function querySummaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        return $this->summariesFromStates(
            $this->currentStates($lab, $weekEnd),
            $this->weekFilters($lab, $this->weekStart($lab, $weekEnd)),
        );
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    protected function querySummariesForSamples(Lab $lab, CarbonImmutable $weekEnd, array $sampleIds): array
    {
        return $this->summariesFromStates(
            $this->currentStates($lab, $weekEnd, $sampleIds),
            $this->weekFilters($lab, $this->weekStart($lab, $weekEnd), $sampleIds),
        );
    }

    /**
     * @return array<int, BacteriaState>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        return $this->sampleStates($sample, $weekEnd)
            ->filter(fn (array $state): bool => $this->isPresent($state))
            ->map(fn (array $state): BacteriaState => $this->bacteriumState($state))
            ->values()
            ->all();
    }

    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?SampleFilter
    {
        $sample->loadMissing('lab.organization');

        $weekStart = $this->weekStart($sample->lab, $weekEnd);

        $row = DB::table('filters as f')
            ->leftJoin('filter_observations as fo', function ($join) use ($weekStart): void {
                $join->on('fo.filter_id', '=', 'f.id')
                    ->where('fo.week_start', '=', $weekStart);
            })
            ->where('f.sample_id', $sample->id)
            ->where('f.week_start', '<=', $weekStart)
            ->select('f.iso_year', 'f.iso_week', 'f.placed_at', 'fo.value')
            ->orderByDesc('fo.observed_at')
            ->orderByDesc('fo.id')
            ->first();

        if ($row === null) {
            return null;
        }

        return new SampleFilter(
            sprintf('%d-W%02d', (int) $row->iso_year, (int) $row->iso_week),
            CarbonImmutable::parse((string) $row->placed_at)->toIso8601String(),
            $row->value !== null ? (int) $row->value : null,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $states
     * @param  array<int, array{value: int|null}>  $filters
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    private function summariesFromStates(Collection $states, array $filters): array
    {
        $summaries = [];

        foreach ($states->groupBy('sample_id') as $sampleId => $sampleStates) {
            $present = $sampleStates->filter(fn (array $state): bool => $this->isPresent($state))->values();

            if ($present->isEmpty()) {
                continue;
            }

            $levels = $present->filter(fn (array $state): bool => $state['observation_type'] === BacteriaObservationType::Level->value);
            $counts = $present->filter(fn (array $state): bool => $state['observation_type'] === BacteriaObservationType::Count->value);

            $highestLevel = $levels
                ->map(fn (array $state): ?GrowthLevel => GrowthLevel::tryFrom((string) $state['growth_level']))
                ->filter()
                ->sortByDesc(fn (GrowthLevel $level): int => $level->rank())
                ->first();

            $maxCount = $counts->max('count');
            $single = $present->count() === 1 ? $present->first() : null;

            $summaries[(int) $sampleId] = [
                'present_count' => $present->count(),
                'level_count' => $levels->count(),
                'count_count' => $counts->count(),
                'highest_level' => $highestLevel?->value,
                'max_count' => $maxCount !== null ? (int) $maxCount : null,
                'single' => $single === null ? null : [
                    'id' => (int) $single['bacteria_id'],
                    'observation_type' => (string) $single['observation_type'],
                    'growth_level' => $single['observation_type'] === 'level' ? (string) $single['growth_level'] : null,
                    'count' => $single['observation_type'] === 'count' ? (int) $single['count'] : null,
                ],
            ];
        }

        foreach ($filters as $sampleId => $filter) {
            $summaries[$sampleId] = ($summaries[$sampleId] ?? [
                'present_count' => 0,
                'level_count' => 0,
                'count_count' => 0,
                'highest_level' => null,
                'max_count' => null,
                'single' => null,
            ]) + ['filter' => $filter];
        }

        return $summaries;
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, array{value: int|null}>
     */
    private function weekFilters(Lab $lab, string $weekStart, array $sampleIds = []): array
    {
        return DB::table('filters as f')
            ->leftJoin('filter_observations as fo', function ($join) use ($weekStart): void {
                $join->on('fo.filter_id', '=', 'f.id')
                    ->where('fo.week_start', '=', $weekStart);
            })
            ->where('f.lab_id', $lab->id)
            ->when($sampleIds !== [], fn ($query) => $query->whereIn('f.sample_id', $sampleIds))
            ->where('f.week_start', '<=', $weekStart)
            ->select('f.sample_id', 'fo.value')
            ->orderBy('f.sample_id')
            ->orderByDesc('fo.observed_at')
            ->orderByDesc('fo.id')
            ->distinct('f.sample_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->sample_id => ['value' => $row->value !== null ? (int) $row->value : null],
            ])
            ->all();
    }

    private function weekStart(Lab $lab, CarbonImmutable $weekEnd): string
    {
        return ObservationWeek::for($weekEnd, $lab->organization->timezone)->startDate;
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return Collection<int, array<string, mixed>>
     */
    private function currentStates(Lab $lab, CarbonImmutable $weekEnd, array $sampleIds = []): Collection
    {
        return $this->statesQuery($weekEnd, $lab->id, null, $sampleIds);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sampleStates(Sample $sample, CarbonImmutable $weekEnd): Collection
    {
        return $this->statesQuery($weekEnd, null, $sample->id);
    }

    /**
     * One row per sample+bacterium with the latest state before the week end.
     *
     * `bacteria_observations` carries a denormalized sample_id/observed_at and
     * the state index, so each (sample, bacterium) is one index probe instead
     * of a full scan plus a 20M-row sort.
     *
     * @param  array<int, int>  $sampleIds
     * @return Collection<int, array<string, mixed>>
     */
    private function statesQuery(
        CarbonImmutable $weekEnd,
        ?int $labId = null,
        ?int $sampleId = null,
        array $sampleIds = [],
    ): Collection {
        if ($sampleIds !== []) {
            $scope = 's.id IN ('.implode(', ', array_fill(0, count($sampleIds), '?')).')';
            $bindings = [$weekEnd, ...$sampleIds];
        } elseif ($sampleId !== null) {
            $scope = 's.id = ?';
            $bindings = [$weekEnd, $sampleId];
        } else {
            $scope = 's.lab_id = ?';
            $bindings = [$weekEnd, $labId];
        }

        return collect(DB::select(sprintf(<<<'SQL'
SELECT
    s.id AS sample_id,
    b.id AS bacteria_id,
    x.growth_level,
    x.count,
    b.name,
    b.short_name,
    b.color,
    b.icon,
    b.observation_type
FROM samples AS s
CROSS JOIN bacteria AS b
INNER JOIN LATERAL (
    SELECT bo.growth_level, bo.count
    FROM bacteria_observations AS bo
    WHERE bo.sample_id = s.id
      AND bo.bacteria_id = b.id
      AND bo.observed_at < ?
    ORDER BY bo.observed_at DESC, bo.id DESC
    LIMIT 1
) AS x ON TRUE
WHERE %s
ORDER BY s.id, b.id
SQL, $scope), $bindings))
            ->map(fn (object $row): array => $this->stateRow($row))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function stateRow(object $row): array
    {
        return (array) $row;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function isPresent(array $state): bool
    {
        if ($state['observation_type'] === BacteriaObservationType::Count->value) {
            return (int) $state['count'] > 0;
        }

        return in_array($state['growth_level'], [
            GrowthLevel::High->value,
            GrowthLevel::Medium->value,
            GrowthLevel::Low->value,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function bacteriumState(array $state): BacteriaState
    {
        return new BacteriaState(
            (int) $state['bacteria_id'],
            (string) $state['name'],
            (string) $state['short_name'],
            (string) $state['color'],
            (string) $state['icon'],
            (string) $state['observation_type'],
            $state['growth_level'] !== null ? (string) $state['growth_level'] : null,
            $state['count'] !== null ? (int) $state['count'] : null,
        );
    }
}
