<?php

namespace App\Actions;

use App\Data\LabMap\BacteriaState;
use App\Data\LabMap\SampleFilter;
use App\Data\LabMap\WeekOption;
use App\Enums\LabMapEngine;
use App\Models\Lab;
use App\Services\LabMap\LabMapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class BuildLabMapPage
{
    public function __construct(
        private readonly LabMapService $map,
    ) {}

    /**
     * @return array{
     *     samples_count: int,
     *     weeks: array<int, WeekOption>,
     *     selectedWeek: WeekOption,
     *     rows: array<int, array<string, mixed>>,
     *     engine: string,
     *     engines: array<int, array{value: string, label: string}>,
     *     timings: array<string, array{lab_id: int, week: string, ms: float, samples: int}>,
     *     engineError: string|null
     * }
     */
    public function handle(Request $request, Lab $lab): array
    {
        $weeks = $this->map->weekOptions($lab);
        $weekParam = $request->query('week');
        $selectedWeek = $this->map->selectedWeek($weeks, is_string($weekParam) ? $weekParam : null);
        $weekEnd = $this->map->weekEnd($lab, $selectedWeek);
        $engine = $this->map->engine();
        $hierarchy = $this->map->hierarchy($lab);

        $queryStartedAt = microtime(true);
        $engineError = null;

        try {
            $summaries = $this->map->summaries($lab, $weekEnd, $hierarchy);
        } catch (Throwable $exception) {
            report($exception);

            $summaries = [];
            $engineError = $this->engineError($engine);
        }

        $queryMs = round((microtime(true) - $queryStartedAt) * 1000, 1);
        $timings = $this->sessionTimings($request);

        if ($engineError === null) {
            $timings[$engine->value] = [
                'lab_id' => $lab->id,
                'week' => $selectedWeek->value,
                'ms' => $queryMs,
                'samples' => count($summaries),
            ];

            $request->session()->put('lab_map_timings', $timings);
        }

        return [
            'samples_count' => count($hierarchy->sampleIds),
            'weeks' => $weeks,
            'selectedWeek' => $selectedWeek,
            'rows' => $this->mapHierarchy($hierarchy->records, $summaries),
            'engine' => $engine->value,
            'engines' => array_map(fn (LabMapEngine $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ], LabMapEngine::cases()),
            'timings' => $timings,
            'engineError' => $engineError,
        ];
    }

    /**
     * @return array{
     *     sample: array{id: int, code: string},
     *     week: WeekOption,
     *     bacteria: array<int, BacteriaState>,
     *     filter: SampleFilter|null
     * }
     */
    public function sampleDetails(Request $request, Lab $lab): array
    {
        $sample = $lab->samples()->findOrFail((int) $request->input('sample', 0));
        [$selectedWeek, $weekEnd] = $this->weekContext($request, $lab);

        return [
            'sample' => [
                'id' => $sample->id,
                'code' => $sample->code,
            ],
            'week' => $selectedWeek,
            'bacteria' => $this->map->sampleBacteria($sample, $weekEnd),
            'filter' => $this->map->sampleFilter($sample, $weekEnd),
        ];
    }

    /**
     * @return array{WeekOption, CarbonImmutable}
     */
    private function weekContext(Request $request, Lab $lab): array
    {
        $weeks = $this->map->weekOptions($lab);
        $weekParam = $request->query('week');
        $selectedWeek = $this->map->selectedWeek($weeks, is_string($weekParam) ? $weekParam : null);

        return [$selectedWeek, $this->map->weekEnd($lab, $selectedWeek)];
    }

    private function engineError(LabMapEngine $engine): string
    {
        return $engine === LabMapEngine::Postgres
            ? (string) __('The :engine lab map is unavailable.', ['engine' => $engine->label()])
            : (string) __('The :engine lab map is unavailable. Run php artisan clickhouse:load to load the ClickHouse dataset.', ['engine' => $engine->label()]);
    }

    /**
     * @return array<string, array{lab_id: int, week: string, ms: float, samples: int}>
     */
    private function sessionTimings(Request $request): array
    {
        $timings = [];

        foreach ((array) $request->session()->get('lab_map_timings', []) as $engine => $timing) {
            if (! is_array($timing) || ! isset($timing['lab_id'], $timing['week'], $timing['ms'])) {
                continue;
            }

            $timings[(string) $engine] = [
                'lab_id' => (int) $timing['lab_id'],
                'week' => (string) $timing['week'],
                'ms' => (float) $timing['ms'],
                'samples' => (int) ($timing['samples'] ?? 0),
            ];
        }

        return $timings;
    }

    /**
     * @param  Collection<int, \stdClass>  $records
     * @param  array<int, array{present_count: int, level_count: int, count_count: int, highest_level: string|null, max_count: int|null, single: array{id: int, observation_type: string, growth_level: string|null, count: int|null}|null, filter?: array{value: int|null}}>  $summaries
     * @return array<int, array<string, mixed>>
     */
    private function mapHierarchy(Collection $records, array $summaries): array
    {
        $rows = [];

        foreach ($records as $record) {
            $rowId = (int) $record->row_id;
            $shelfId = (int) $record->shelf_id;
            $levelId = (int) $record->level_id;

            $rows[$rowId] ??= [
                'id' => $rowId,
                'number' => (int) $record->row_number,
                'shelves' => [],
            ];

            $rows[$rowId]['shelves'][$shelfId] ??= [
                'id' => $shelfId,
                'number' => (int) $record->shelf_number,
                'levels' => [],
            ];

            $rows[$rowId]['shelves'][$shelfId]['levels'][$levelId] ??= [
                'id' => $levelId,
                'number' => (int) $record->level_number,
                'samples' => [],
            ];

            if ($record->sample_id !== null) {
                $sampleId = (int) $record->sample_id;

                $rows[$rowId]['shelves'][$shelfId]['levels'][$levelId]['samples'][] = [
                    'id' => $sampleId,
                    'code' => (string) $record->sample_code,
                    'summary' => $summaries[$sampleId] ?? [
                        'present_count' => 0,
                        'level_count' => 0,
                        'count_count' => 0,
                        'highest_level' => null,
                        'max_count' => null,
                        'single' => null,
                    ],
                ];
            }
        }

        foreach ($rows as &$row) {
            $row['shelves'] = array_values($row['shelves']);

            foreach ($row['shelves'] as &$shelf) {
                $shelf['levels'] = array_values($shelf['levels']);
            }
        }

        unset($row, $shelf);

        return array_values($rows);
    }
}
