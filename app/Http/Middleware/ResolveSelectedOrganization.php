<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSelectedOrganization
{
    /**
     * Resolve the organization selected in the session for the authenticated user.
     *
     * Falls back to the user's first organization when the session holds no valid
     * selection, and keeps that fallback in the session so it survives the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $organizations = $user->organizations()->orderBy('name')->get();
            $selectedId = $request->session()->get('selected_organization_id');
            $selected = $organizations->firstWhere('id', $selectedId) ?? $organizations->first();

            if ($selected instanceof Organization && $selected->id !== $selectedId) {
                $request->session()->put('selected_organization_id', $selected->id);
            }

            $request->attributes->set('selectedOrganization', $selected);
            $request->attributes->set('userOrganizations', $organizations);
        }

        return $next($request);
    }
}
