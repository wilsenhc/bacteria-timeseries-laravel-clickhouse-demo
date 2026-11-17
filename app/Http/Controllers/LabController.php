<?php

namespace App\Http\Controllers;

use App\Actions\BuildLabMapPage;
use App\Actions\CreateLab;
use App\Enums\LabMapEngine;
use App\Http\Requests\StoreLabRequest;
use App\Http\Requests\UpdateLabRequest;
use App\Models\Lab;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
    public function show(Request $request, Lab $lab, BuildLabMapPage $buildPage): Response
    {
        Gate::authorize('view', $lab);

        $organization = $this->ensureInSelectedOrganization($request, $lab);
        $lab->setRelation('organization', $organization);

        $partialData = (string) $request->header('X-Inertia-Partial-Data', '');

        if (str_contains($partialData, 'sampleDetails')) {
            return Inertia::render('labs/Show', [
                'sampleDetails' => Inertia::optional(fn (): array => $buildPage->sampleDetails($request, $lab)),
            ]);
        }

        $data = $buildPage->handle($request, $lab);

        $props = [
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
                'samples_count' => $data['samples_count'],
                'created_at' => $lab->created_at?->toIso8601String(),
            ],
            'weeks' => $data['weeks'],
            'selectedWeek' => $data['selectedWeek'],
            'rows' => $data['rows'],
            'canManage' => $request->user()->can('update', $lab),
            'engine' => $data['engine'],
            'engines' => $data['engines'],
            'timings' => $data['timings'],
            'engineError' => $data['engineError'],
            'sampleDetails' => Inertia::optional(fn (): array => $buildPage->sampleDetails($request, $lab)),
        ];

        return Inertia::render('labs/Show', $props);
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
}
