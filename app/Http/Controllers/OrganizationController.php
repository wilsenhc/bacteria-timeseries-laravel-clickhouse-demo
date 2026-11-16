<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * List the organizations the authenticated user can manage.
     */
    public function index(Request $request): Response
    {
        $organizations = $request->user()
            ->organizations()
            ->withCount('labs')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization): array {
                $pivot = $organization->getRelation('pivot');

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'address' => $organization->address,
                    'timezone' => $organization->timezone,
                    'role' => $pivot instanceof OrganizationMembership ? $pivot->role->value : null,
                    'labs_count' => $organization->labs_count,
                ];
            });

        return Inertia::render('organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Show the organization creation form.
     */
    public function create(): Response
    {
        return Inertia::render('organizations/Create');
    }

    /**
     * Create an organization and make the creator its owner.
     */
    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = DB::transaction(function () use ($request): Organization {
            $organization = Organization::create($request->validated());

            $organization->users()->attach($request->user()->id, [
                'role' => OrganizationRole::Owner->value,
            ]);

            return $organization;
        });

        $request->session()->put('selected_organization_id', $organization->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('organizations.index');
    }

    /**
     * Select the organization used by the labs area for this session.
     */
    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
        ]);

        $organization = $request->user()
            ->organizations()
            ->whereKey($validated['organization_id'])
            ->first();

        abort_if($organization === null, 403);

        $request->session()->put('selected_organization_id', $organization->id);

        return to_route('labs.index');
    }

    /**
     * Show the organization edit form.
     */
    public function edit(Request $request, Organization $organization): Response
    {
        Gate::authorize('update', $organization);

        return Inertia::render('organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'address' => $organization->address,
                'timezone' => $organization->timezone,
            ],
        ]);
    }

    /**
     * Update an organization.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return to_route('organizations.index');
    }

    /**
     * Delete an organization and everything that belongs to it.
     */
    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        $organization->delete();

        if ($request->session()->get('selected_organization_id') === $organization->id) {
            $request->session()->forget('selected_organization_id');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route('organizations.index');
    }
}
