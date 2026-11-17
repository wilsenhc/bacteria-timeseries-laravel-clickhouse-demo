<?php

namespace App\Support\LabMap;

use App\Models\Lab;
use App\Models\Sample;
use Carbon\CarbonImmutable;

interface LabMapEngineContract
{
    /**
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    public function summaries(Lab $lab, CarbonImmutable $weekEnd): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sampleBacteria(Sample $sample, CarbonImmutable $weekEnd): array;

    /**
     * @return array{placed_week: string, placed_at: string, value: int|null}|null
     */
    public function sampleFilter(Sample $sample, CarbonImmutable $weekEnd): ?array;
}
