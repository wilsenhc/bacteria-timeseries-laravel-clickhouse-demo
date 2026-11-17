<?php

namespace App\Support\LabMap;

use Carbon\CarbonImmutable;

trait MapsClickHouseLabMapRows
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

            $summary = [
                'present_count' => $presentCount,
                'level_count' => $levelCount,
                'count_count' => $countCount,
                'highest_level' => $levelCount > 0 ? (string) $row['highest_level'] : null,
                'max_count' => $countCount > 0 ? (int) $row['max_count'] : null,
                'single' => $singleType === null ? null : [
                    'id' => (int) $row['single_id'],
                    'observation_type' => $singleType,
                    'growth_level' => $singleType === 'level' ? (string) $row['single_growth_level'] : null,
                    'count' => $singleType === 'count' ? (int) $row['single_count'] : null,
                ],
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
     * @return array{placed_week: string, placed_at: string, value: int|null}|null
     */
    protected function mapFilter(array $rows): ?array
    {
        $row = $rows[0] ?? null;

        if ($row === null) {
            return null;
        }

        return [
            'placed_week' => sprintf('%d-W%02d', (int) $row['placed_iso_year'], (int) $row['placed_iso_week']),
            'placed_at' => CarbonImmutable::parse((string) $row['placed_at'])->toIso8601String(),
            'value' => (int) $row['filter_value'] >= 0 ? (int) $row['filter_value'] : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mapBacteria(array $rows): array
    {
        return array_map(function (array $row): array {
            $observationType = (string) $row['observation_type'];

            return [
                'id' => (int) $row['bacteria_id'],
                'name' => (string) $row['bacteria_name'],
                'short_name' => (string) $row['bacteria_short_name'],
                'color' => (string) $row['bacteria_color'],
                'icon' => (string) $row['bacteria_icon'],
                'observation_type' => $observationType,
                'growth_level' => $observationType === 'level' ? (string) $row['growth_level'] : null,
                'count' => $observationType === 'count' ? (int) $row['count'] : null,
            ];
        }, $rows);
    }
}
