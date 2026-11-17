<?php

namespace App\Http\Controllers;

use App\Actions\CreateLab;
use App\Enums\LabMapEngine;
use App\Http\Requests\StoreLabRequest;
use App\Http\Requests\UpdateLabRequest;
use App\Models\Lab;
use App\Models\Organization;
use App\Support\LabMapQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LabController extends Controller
{
    /**
     * List the labs of the selected organization.
     */
    public function index(Request $request): Response
    {
        $organization = $this->selectedOrganization($request);

        return Inertia::render('labs/Index', [
            'organization' => $organization === null ? null : [
                'id' => $organization->id,
                'name' => $organization->name,
                'address' => $organization->address,
                'timezone' => $organization->timezone,
                'role' => $request->user()->roleIn($organization)?->value,
            ],
            'labs' => $organization === null ? [] : $organization->labs()
                ->withCount(['rows', 'samples'])
                ->orderBy('name')
                ->get()
                ->map(fn (Lab $lab): array => $this->labSummary($lab))
                ->all(),
            'canManage' => $organization !== null
                && $request->user()->can('create', [Lab::class, $organization]),
        ]);
    }

    /**
     * Show the lab creation form for the selected organization.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $organization = $this->selectedOrganization($request);

        if ($organization === null) {
            return $this->missingOrganization();
        }

        Gate::authorize('create', [Lab::class, $organization]);

        return Inertia::render('labs/Create', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }

    /**
     * Create a lab and its hierarchy under the selected organization.
     */
    public function store(StoreLabRequest $request, CreateLab $createLab): RedirectResponse
    {
        $organization = $this->selectedOrganization($request);

        if ($organization === null) {
            return $this->missingOrganization();
        }

        Gate::authorize('create', [Lab::class, $organization]);

        $lab = $createLab->handle($organization, $request->labData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lab created.')]);

        return to_route('labs.show', $lab);
    }

    /**
     * Show the full lab map.
     */
    public function show(Request $request, Lab $lab, LabMapQuery $mapQuery): Response
    {
        Gate::authorize('view', $lab);

        $organization = $this->ensureInSelectedOrganization($request, $lab);
        $lab->setRelation('organization', $organization);

        $weeks = $mapQuery->weekOptions($lab);
        $weekParam = $request->query('week');
        $selectedWeek = $mapQuery->selectedWeek($weeks, is_string($weekParam) ? $weekParam : null);
        $weekEnd = $mapQuery->weekEnd($lab, $selectedWeek);
        $engine = $mapQuery->engine();

        $hierarchy = $this->labHierarchy($lab);

        $queryStartedAt = microtime(true);
        $engineError = null;

        try {
            $summaries = $mapQuery->summaries($lab, $weekEnd, $hierarchy['sample_ids']);
        } catch (Throwable $exception) {
            report($exception);

            $summaries = [];
            $engineError = __('The :engine lab map is unavailable. Run php artisan ch:load to load the ClickHouse dataset.', [
                'engine' => $engine->label(),
            ]);
        }

        $queryMs = round((microtime(true) - $queryStartedAt) * 1000, 1);

        $timings = $request->session()->get('lab_map_timings', []);

        if ($engineError === null) {
            $timings[$engine->value] = [
                'lab_id' => $lab->id,
                'week' => $selectedWeek['value'],
                'ms' => $queryMs,
            ];

            $request->session()->put('lab_map_timings', $timings);
        }

        $rows = $this->mapHierarchy($hierarchy['records'], $summaries, $mapQuery);

        return Inertia::render('labs/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'lab' => [
                'id' => $lab->id,
                'name' => $lab->name,
                'code' => $lab->code,
                'notes' => $lab->notes,
                'row_count' => $lab->row_count,
                'shelves_per_row' => $lab->shelves_per_row,
                'levels_per_shelf' => $lab->levels_per_shelf,
                'levels_count' => $lab->levelsCount(),
                'samples_count' => count($hierarchy['sample_ids']),
                'created_at' => $lab->created_at?->toIso8601String(),
            ],
            'weeks' => $weeks,
            'selectedWeek' => $selectedWeek,
            'rows' => $rows,
            'canManage' => $request->user()->can('update', $lab),
            'engine' => $engine->value,
            'timings' => $timings,
            'engineError' => $engineError,
        ]);
    }

    /**
     * Switch the lab map query engine for this session.
     */
    public function engine(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'engine' => ['required', Rule::enum(LabMapEngine::class)],
        ]);

        $request->session()->put('lab_map_engine', $validated['engine']);

        return back();
    }

    /**
     * Show the lab edit form.
     */
    public function edit(Request $request, Lab $lab): Response
    {
        Gate::authorize('update', $lab);

        $this->ensureInSelectedOrganization($request, $lab);

        return Inertia::render('labs/Edit', [
            'lab' => [
                'id' => $lab->id,
                'name' => $lab->name,
                'code' => $lab->code,
                'notes' => $lab->notes,
                'row_count' => $lab->row_count,
                'shelves_per_row' => $lab->shelves_per_row,
                'levels_per_shelf' => $lab->levels_per_shelf,
            ],
        ]);
    }

    /**
     * Update a lab's metadata. The structure counts are immutable.
     */
    public function update(UpdateLabRequest $request, Lab $lab): RedirectResponse
    {
        Gate::authorize('update', $lab);

        $this->ensureInSelectedOrganization($request, $lab);

        $lab->update($request->labData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lab updated.')]);

        return to_route('labs.show', $lab);
    }

    /**
     * Delete a lab and everything that belongs to it.
     */
    public function destroy(Request $request, Lab $lab): RedirectResponse
    {
        Gate::authorize('delete', $lab);

        $this->ensureInSelectedOrganization($request, $lab);

        $lab->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lab deleted.')]);

        return to_route('labs.index');
    }

    /**
     * @return array{id: int, name: string, code: string|null, row_count: int, shelves_per_row: int, levels_per_shelf: int, levels_count: int, samples_count: int, created_at: string|null}
     */
    private function labSummary(Lab $lab): array
    {
        return [
            'id' => $lab->id,
            'name' => $lab->name,
            'code' => $lab->code,
            'row_count' => $lab->row_count,
            'shelves_per_row' => $lab->shelves_per_row,
            'levels_per_shelf' => $lab->levels_per_shelf,
            'levels_count' => $lab->levelsCount(),
            'samples_count' => $lab->samples_count,
            'created_at' => $lab->created_at?->toIso8601String(),
        ];
    }

    private function selectedOrganization(Request $request): ?Organization
    {
        $organization = $request->attributes->get('selectedOrganization');

        return $organization instanceof Organization ? $organization : null;
    }

    private function ensureInSelectedOrganization(Request $request, Lab $lab): Organization
    {
        $organization = $this->selectedOrganization($request);

        abort_unless($organization !== null && $lab->organization_id === $organization->id, 404);

        return $organization;
    }

    private function missingOrganization(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('Create an organization first.')]);

        return to_route('organizations.index');
    }

    /**
     * @return array{records: Collection<int, \stdClass>, sample_ids: array<int, int>}
     */
    private function labHierarchy(Lab $lab): array
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

        return ['records' => $records, 'sample_ids' => $sampleIds];
    }

    /**
     * @param  Collection<int, \stdClass>  $records
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array<int, array<string, mixed>>
     */
    private function mapHierarchy(Collection $records, array $summaries, LabMapQuery $mapQuery): array
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
                    'summary' => $summaries[$sampleId] ?? $mapQuery->emptySummary(),
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
