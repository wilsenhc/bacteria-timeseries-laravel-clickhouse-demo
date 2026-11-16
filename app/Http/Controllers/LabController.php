<?php

namespace App\Http\Controllers;

use App\Actions\CreateLab;
use App\Http\Requests\StoreLabRequest;
use App\Http\Requests\UpdateLabRequest;
use App\Models\Lab;
use App\Models\LabLevel;
use App\Models\LabRow;
use App\Models\LabShelf;
use App\Models\Organization;
use App\Models\Sample;
use App\Support\LabMapQuery;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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

        $lab->load([
            'organization',
            'rows' => fn (HasMany $query) => $query->orderBy('number'),
            'rows.shelves' => fn (HasMany $query) => $query->orderBy('number'),
            'rows.shelves.levels' => fn (HasMany $query) => $query->orderBy('number'),
            'rows.shelves.levels.samples' => fn (HasMany $query) => $query->orderBy('code'),
        ]);

        $weeks = $mapQuery->weekOptions($lab);
        $weekParam = $request->query('week');
        $selectedWeek = $mapQuery->selectedWeek($weeks, is_string($weekParam) ? $weekParam : null);
        $weekEnd = $mapQuery->weekEnd($lab, $selectedWeek);
        $summaries = $mapQuery->summaries($lab, $weekEnd);

        $rows = $lab->rows
            ->map(fn (LabRow $row): array => $this->mapRow($row, $summaries, $mapQuery))
            ->all();

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
                'samples_count' => $lab->samples()->count(),
                'created_at' => $lab->created_at?->toIso8601String(),
            ],
            'weeks' => $weeks,
            'selectedWeek' => $selectedWeek,
            'rows' => $rows,
            'canManage' => $request->user()->can('update', $lab),
        ]);
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
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array{id: int, number: int, shelves: array<int, array{id: int, number: int, levels: array<int, array{id: int, number: int, samples: array<int, array{id: int, code: string, summary: array<string, mixed>}>}>}>}
     */
    private function mapRow(LabRow $row, array $summaries, LabMapQuery $mapQuery): array
    {
        return [
            'id' => $row->id,
            'number' => $row->number,
            'shelves' => $row->shelves
                ->map(fn (LabShelf $shelf): array => $this->mapShelf($shelf, $summaries, $mapQuery))
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array{id: int, number: int, levels: array<int, array{id: int, number: int, samples: array<int, array{id: int, code: string, summary: array<string, mixed>}>}>}
     */
    private function mapShelf(LabShelf $shelf, array $summaries, LabMapQuery $mapQuery): array
    {
        return [
            'id' => $shelf->id,
            'number' => $shelf->number,
            'levels' => $shelf->levels
                ->map(fn (LabLevel $level): array => $this->mapLevel($level, $summaries, $mapQuery))
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array{id: int, number: int, samples: array<int, array{id: int, code: string, summary: array<string, mixed>}>}
     */
    private function mapLevel(LabLevel $level, array $summaries, LabMapQuery $mapQuery): array
    {
        return [
            'id' => $level->id,
            'number' => $level->number,
            'samples' => $level->samples
                ->map(fn (Sample $sample): array => [
                    'id' => $sample->id,
                    'code' => $sample->code,
                    'summary' => $summaries[$sample->id] ?? $mapQuery->emptySummary(),
                ])
                ->all(),
        ];
    }
}
