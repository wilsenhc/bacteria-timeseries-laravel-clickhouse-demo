<?php

namespace App\Services\LabMap;

use App\Data\LabMap\BacteriaState;
use App\Data\LabMap\LabMapHierarchy;
use App\Data\LabMap\SampleFilter;
use App\Data\LabMap\WeekOption;
use App\Enums\LabMapEngine;
use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;

interface LabMapService
{
    public function hierarchy(Lab $lab): LabMapHierarchy;

    /**
     * @return array<int, WeekOption>
     */
    public function weekOptions(Lab $lab): array;

    /**
     * @param  array<int, WeekOption>  $weeks
     */
    public function selectedWeek(array $weeks, ?string $value): WeekOption;

    public function weekEnd(Lab $lab, WeekOption $week): CarbonImmutable;

    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summaries(Lab $lab, CarbonImmutable $weekEnd, LabMapHierarchy $hierarchy): array;

    /**
     * @param  array<int, int>  $sampleIds
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summariesForSamples(Lab $lab, CarbonImmutable $weekEnd, array $sampleIds): array;

    /**
     * @return array<int, BacteriaState>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array;

    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?SampleFilter;

    public function engine(): LabMapEngine;
}
