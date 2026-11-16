<?php

namespace App\Support;

use App\Enums\BacteriaObservationType;
use App\Enums\GrowthLevel;
use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LabMapQuery
{
    /**
     * @return array<int, array{value: string, label: string, is_current: bool}>
     */
    public function weekOptions(Lab $lab): array
    {
        $timezone = $lab->organization->timezone;
        $createdAt = $lab->created_at?->toImmutable() ?? CarbonImmutable::now($timezone);
        $start = $createdAt->setTimezone($timezone)->startOfWeek(CarbonInterface::MONDAY);
        $current = CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY);

        $weeks = [];

        for ($week = $start; $week->lessThanOrEqualTo($current); $week = $week->addWeek()) {
            $weeks[] = [
                'value' => sprintf('%d-W%02d', $week->isoWeekYear, $week->isoWeek),
                'label' => sprintf(
                    '%d-W%02d · %s–%s',
                    $week->isoWeekYear,
                    $week->isoWeek,
                    $week->isoFormat('MMM D'),
                    $week->addDays(6)->isoFormat('MMM D'),
                ),
                'is_current' => $week->isSameWeek($current),
            ];
        }

        return array_reverse($weeks);
    }

    /**
     * @param  array<int, array{value: string, label: string, is_current: bool}>  $weeks
     * @return array{value: string, label: string, is_current: bool}
     */
    public function selectedWeek(array $weeks, ?string $value): array
    {
        if ($value !== null) {
            foreach ($weeks as $week) {
                if ($week['value'] === $value) {
                    return $week;
                }
            }
        }

        return $weeks[0];
    }

    /**
     * @param  array{value: string, label: string, is_current: bool}  $week
     */
    public function weekEnd(Lab $lab, array $week): CarbonImmutable
    {
        [$year, $weekNumber] = $this->parseWeekValue($week['value']);

        return CarbonImmutable::now($lab->organization->timezone)
            ->setISODate($year, $weekNumber, 1)
            ->startOfDay()
            ->addDays(6)
            ->endOfDay()
            ->utc();
    }

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single_bacterium: array<string, mixed>|null}>
     */
    public function summaries(Lab $lab, CarbonImmutable $weekEnd): array
    {
        $summaries = $lab->samples()
            ->pluck('id')
            ->mapWithKeys(fn (int $sampleId): array => [$sampleId => $this->emptySummary()])
            ->all();

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
                'single_bacterium' => $single !== null ? $this->bacteriumPayload($single) : null,
            ];
        }

        return $summaries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        return $this->sampleStates($sample, $weekEnd)
            ->filter(fn (array $state): bool => $this->isPresent($state))
            ->map(fn (array $state): array => $this->bacteriumPayload($state))
            ->values()
            ->all();
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

    /**
     * @return array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single_bacterium: array<string, mixed>|null}
     */
    public function emptySummary(): array
    {
        return [
            'present_count' => 0,
            'level_count' => 0,
            'count_count' => 0,
            'highest_level' => null,
            'max_count' => null,
            'single_bacterium' => null,
        ];
    }

    /**
     * @return array{int, int}
     */
    private function parseWeekValue(string $value): array
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid ISO week value.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
