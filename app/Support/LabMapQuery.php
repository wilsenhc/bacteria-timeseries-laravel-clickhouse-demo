<?php

namespace App\Support;

use App\Enums\LabMapEngine;
use App\Models\Lab;
use App\Models\Sample;
use App\Support\LabMap\LabMapEngineManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class LabMapQuery
{
    public function __construct(
        private readonly LabMapEngineManager $engines,
    ) {}

    public function engine(): LabMapEngine
    {
        return $this->engines->selected();
    }

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
     * @param  array<int, int>|null  $sampleIds  Sample ids from the already-loaded hierarchy.
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summaries(Lab $lab, CarbonImmutable $weekEnd, ?array $sampleIds = null): array
    {
        $sampleIds ??= $lab->samples()->pluck('id')->all();
        $summaries = array_fill_keys($sampleIds, $this->emptySummary());

        return array_replace($summaries, $this->engines->current()->summaries($lab, $weekEnd));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array
    {
        return $this->engines->current()->sampleBacteria($sample, $weekEnd);
    }

    /**
     * @return array{placed_week: string, placed_at: string, value: int|null}|null
     */
    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?array
    {
        return $this->engines->current()->sampleFilter($sample, $weekEnd);
    }

    /**
     * @return array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null}
     */
    public function emptySummary(): array
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
