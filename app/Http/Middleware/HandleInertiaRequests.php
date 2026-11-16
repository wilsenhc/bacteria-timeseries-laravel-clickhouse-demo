<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'organizationSwitch' => fn (): array => $this->organizationSwitch($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The selected organization and the organizations the user can switch to.
     *
     * @return array{selected: array{id: int, name: string}|null, options: array<int, array{id: int, name: string}>}
     */
    private function organizationSwitch(Request $request): array
    {
        $selected = $request->attributes->get('selectedOrganization');
        $organizations = $request->attributes->get('userOrganizations');

        return [
            'selected' => $selected instanceof Organization
                ? ['id' => $selected->id, 'name' => $selected->name]
                : null,
            'options' => $organizations instanceof Collection
                ? $organizations
                    ->map(fn (Organization $organization): array => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }
}
