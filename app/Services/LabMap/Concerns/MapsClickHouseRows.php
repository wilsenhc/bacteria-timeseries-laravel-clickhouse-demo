<?php

namespace App\Services\LabMap\Concerns;

use App\Data\LabMap\BacteriaState;
use App\Data\LabMap\SampleFilter;
use Carbon\CarbonImmutable;

trait MapsClickHouseRows
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>
     */
    protected function mapSummaries(array $rows): array
    {
        $summaries = [];

        foreach ($rows as $row) {
            $sampleId = (int) $row['sample_id'];
            $presentCount = (int) $row['present_count'];
            $filterId = (int) ($row['filter_id'] ?? 0);

            if ($presentCount === 0 && $filterId === 0) {
                continue;
            }

            $levelCount = (int) $row['level_count'];
            $countCount = (int) $row['count_count'];
            $singleType = $presentCount === 1 ? (string) $row['single_observation_type'] : null;

            $single = $singleType === null ? null : [
                'id' => (int) $row['single_id'],
                'observation_type' => $singleType,
                'growth_level' => $singleType === 'level' ? (string) $row['single_growth_level'] : null,
                'count' => $singleType === 'count' ? (int) $row['single_count'] : null,
            ];

            $summary = [
                'present_count' => $presentCount,
                'level_count' => $levelCount,
                'count_count' => $countCount,
                'highest_level' => $levelCount > 0 ? (string) $row['highest_level'] : null,
                'max_count' => $countCount > 0 ? (int) $row['max_count'] : null,
                'single' => $single,
            ];

            if ($filterId > 0) {
                $summary['filter'] = [
                    'value' => (int) $row['filter_value'] >= 0 ? (int) $row['filter_value'] : null,
                ];
            }

            $summaries[$sampleId] = $summary;
        }

        return $summaries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, BacteriaState>
     */
    protected function mapBacteria(array $rows): array
    {
        return array_map(function (array $row): BacteriaState {
            $observationType = (string) $row['observation_type'];

            return new BacteriaState(
                (int) $row['bacteria_id'],
                (string) $row['bacteria_name'],
                (string) $row['bacteria_short_name'],
                (string) $row['bacteria_color'],
                (string) $row['bacteria_icon'],
                $observationType,
                $observationType === 'level' ? (string) $row['growth_level'] : null,
                $observationType === 'count' ? (int) $row['count'] : null,
            );
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function mapFilter(array $rows): ?SampleFilter
    {
        $row = $rows[0] ?? null;

        if ($row === null) {
            return null;
        }

        return new SampleFilter(
            sprintf('%d-W%02d', (int) $row['placed_iso_year'], (int) $row['placed_iso_week']),
            CarbonImmutable::parse((string) $row['placed_at'])->toIso8601String(),
            (int) $row['filter_value'] >= 0 ? (int) $row['filter_value'] : null,
        );
    }
}
