<?php

namespace App\Services\LabMap\Concerns;

use App\Data\LabMap\LabMapHierarchy;
use App\Data\LabMap\WeekOption;
use App\Models\Lab;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

trait ProvidesLabMapPageData
{
    public function hierarchy(Lab $lab): LabMapHierarchy
    {
        $records = DB::table('lab_rows as r')
            ->join('lab_shelves as sh', 'sh.lab_row_id', '=', 'r.id')
            ->join('lab_levels as l', 'l.lab_shelf_id', '=', 'sh.id')
            ->leftJoin('samples as s', 's.lab_level_id', '=', 'l.id')
            ->where('r.lab_id', $lab->id)
            ->orderBy('r.number')
            ->orderBy('sh.number')
            ->orderBy('l.number')
            ->orderBy('s.code')
            ->get([
                'r.id as row_id',
                'r.number as row_number',
                'sh.id as shelf_id',
                'sh.number as shelf_number',
                'l.id as level_id',
                'l.number as level_number',
                's.id as sample_id',
                's.code as sample_code',
            ]);

        $sampleIds = $records
            ->pluck('sample_id')
            ->filter(fn ($sampleId): bool => $sampleId !== null)
            ->map(fn ($sampleId): int => (int) $sampleId)
            ->values()
            ->all();

        return new LabMapHierarchy($records, $sampleIds);
    }

    /**
     * @return array<int, WeekOption>
     */
    public function weekOptions(Lab $lab): array
    {
        $timezone = $lab->organization->timezone;
        $createdAt = $lab->created_at?->toImmutable() ?? CarbonImmutable::now($timezone);
        $start = $createdAt->setTimezone($timezone)->startOfWeek(CarbonInterface::MONDAY);
        $current = CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY);

        $weeks = [];

        for ($week = $start; $week->lessThanOrEqualTo($current); $week = $week->addWeek()) {
            $weeks[] = new WeekOption(
                sprintf('%d-W%02d', $week->isoWeekYear, $week->isoWeek),
                sprintf(
                    '%d-W%02d · %s–%s',
                    $week->isoWeekYear,
                    $week->isoWeek,
                    $week->isoFormat('MMM D'),
                    $week->addDays(6)->isoFormat('MMM D'),
                ),
                $week->isSameWeek($current),
            );
        }

        return array_reverse($weeks);
    }

    /**
     * @param  array<int, WeekOption>  $weeks
     */
    public function selectedWeek(array $weeks, ?string $value): WeekOption
    {
        if ($value !== null) {
            foreach ($weeks as $week) {
                if ($week->value === $value) {
                    return $week;
                }
            }
        }

        return $weeks[0];
    }

    public function weekEnd(Lab $lab, WeekOption $week): CarbonImmutable
    {
        [$year, $weekNumber] = $this->parseWeekValue($week->value);

        return CarbonImmutable::now($lab->organization->timezone)
            ->setISODate($year, $weekNumber, 1)
            ->startOfDay()
            ->addDays(6)
            ->endOfDay()
            ->utc();
    }

    /**
     * @return array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null}
     */
    protected function emptySummary(): array
    {
        return [
            'present_count' => 0,
            'level_count' => 0,
            'count_count' => 0,
            'highest_level' => null,
            'max_count' => null,
            'single' => null,
        ];
    }

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summaries(Lab $lab, CarbonImmutable $weekEnd, LabMapHierarchy $hierarchy): array
    {
        $summaries = array_fill_keys($hierarchy->sampleIds, $this->emptySummary());

        return array_replace($summaries, $this->querySummaries($lab, $weekEnd));
    }

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summariesForSamples(Lab $lab, CarbonImmutable $weekEnd, array $sampleIds): array
    {
        $sampleIds = array_values(array_unique(array_map('intval', $sampleIds)));
        $summaries = array_fill_keys($sampleIds, $this->emptySummary());

        return array_replace($summaries, $this->querySummariesForSamples($lab, $weekEnd, $sampleIds));
    }

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    abstract protected function querySummaries(Lab $lab, CarbonImmutable $weekEnd): array;

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    abstract protected function querySummariesForSamples(Lab $lab, CarbonImmutable $weekEnd, array $sampleIds): array;

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
