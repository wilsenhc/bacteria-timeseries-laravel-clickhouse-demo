<?php

namespace App\Support\LabMap;

use App\Enums\BacteriaObservationType;
use App\Enums\GrowthLevel;
use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostgresLabMapEngine implements LabMapEngineContract
{
    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $summaries = [];

        foreach ($this->currentStates($lab, $weekEnd)->groupBy('sample_id') as $sampleId => $states) {
            $present = $states->filter(fn (array $state): bool => $this->isPresent($state))->values();

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

        foreach ($this->weekFilters($lab, $this->weekStart($lab, $weekEnd)) as $sampleId => $filter) {
            $summaries[$sampleId] ??= [
                'present_count' => 0,
                'level_count' => 0,
                'count_count' => 0,
                'highest_level' => null,
                'max_count' => null,
                'single' => null,
            ];

            $summaries[$sampleId]['filter'] = $filter;
        }

        return $summaries;
    }

    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        return $this->sampleStates($sample, $weekEnd)
            ->filter(fn (array $state): bool => $this->isPresent($state))
            ->map(fn (array $state): array => $this->bacteriumPayload($state))
            ->values()
            ->all();
    }

    /**
     * @return array{placed_week: string, placed_at: string, value: int|null}|null
     */
    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?array
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

        return [
            'placed_week' => sprintf('%d-W%02d', (int) $row->iso_year, (int) $row->iso_week),
            'placed_at' => CarbonImmutable::parse((string) $row->placed_at)->toIso8601String(),
            'value' => $row->value !== null ? (int) $row->value : null,
        ];
    }

    /**
     * @return array<int, array{value: int|null}>
     */
    private function weekFilters(Lab $lab, string $weekStart): array
    {
        return DB::table('filters as f')
            ->leftJoin('filter_observations as fo', function ($join) use ($weekStart): void {
                $join->on('fo.filter_id', '=', 'f.id')
                    ->where('fo.week_start', '=', $weekStart);
            })
            ->where('f.lab_id', $lab->id)
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
        return $weekEnd
            ->setTimezone($lab->organization->timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->toDateString();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function currentStates(Lab $lab, CarbonImmutable $weekEnd): Collection
    {
        return DB::table('bacteria_observations as bo')
            ->join('observations as o', 'o.id', '=', 'bo.observation_id')
            ->join('bacteria as b', 'b.id', '=', 'bo.bacteria_id')
            ->whereIn('o.sample_id', function ($query) use ($lab): void {
                $query->select('id')->from('samples')->where('lab_id', $lab->id);
            })
            ->where('o.observed_at', '<', $weekEnd)
            ->select($this->stateColumns())
            ->orderBy('o.sample_id')
            ->orderBy('bo.bacteria_id')
            ->orderByDesc('o.observed_at')
            ->orderByDesc('o.id')
            ->orderByDesc('bo.id')
            ->distinct('o.sample_id', 'bo.bacteria_id')
            ->get()
            ->map(fn (object $row): array => $this->stateRow($row));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sampleStates(Sample $sample, CarbonImmutable $weekEnd): Collection
    {
        return DB::table('bacteria_observations as bo')
            ->join('observations as o', 'o.id', '=', 'bo.observation_id')
            ->join('bacteria as b', 'b.id', '=', 'bo.bacteria_id')
            ->where('o.sample_id', $sample->id)
            ->where('o.observed_at', '<', $weekEnd)
            ->select($this->stateColumns())
            ->orderBy('bo.bacteria_id')
            ->orderByDesc('o.observed_at')
            ->orderByDesc('o.id')
            ->orderByDesc('bo.id')
            ->distinct('bo.bacteria_id')
            ->get()
            ->map(fn (object $row): array => $this->stateRow($row));
    }

    /**
     * @return array<int, string>
     */
    private function stateColumns(): array
    {
        return [
            'o.sample_id',
            'bo.bacteria_id',
            'bo.growth_level',
            'bo.count',
            'b.name',
            'b.short_name',
            'b.color',
            'b.icon',
            'b.observation_type',
        ];
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
     * @return array<string, mixed>
     */
    private function bacteriumPayload(array $state): array
    {
        return [
            'id' => (int) $state['bacteria_id'],
            'name' => (string) $state['name'],
            'short_name' => (string) $state['short_name'],
            'color' => (string) $state['color'],
            'icon' => (string) $state['icon'],
            'observation_type' => (string) $state['observation_type'],
            'growth_level' => $state['growth_level'] !== null ? (string) $state['growth_level'] : null,
            'count' => $state['count'] !== null ? (int) $state['count'] : null,
        ];
    }
}
