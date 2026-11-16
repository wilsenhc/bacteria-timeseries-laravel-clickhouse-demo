<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Organization;
use App\Models\Sample;
use App\Support\LabMapQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SampleBacteriaController extends Controller
{
    /**
     * Return the present bacteria for one sample at the selected week's end.
     */
    public function __invoke(
        Request $request,
        Lab $lab,
        Sample $sample,
        LabMapQuery $mapQuery,
    ): JsonResponse {
        Gate::authorize('view', $lab);

        $organization = $request->attributes->get('selectedOrganization');

        abort_unless($organization instanceof Organization && $lab->organization_id === $organization->id, 404);
        abort_unless($sample->lab_id === $lab->id, 404);

        $weeks = $mapQuery->weekOptions($lab);
        $weekParam = $request->query('week');
        $selectedWeek = $mapQuery->selectedWeek($weeks, is_string($weekParam) ? $weekParam : null);
        $weekEnd = $mapQuery->weekEnd($lab, $selectedWeek);

        return response()->json([
            'sample' => [
                'id' => $sample->id,
                'code' => $sample->code,
            ],
            'week' => $selectedWeek,
            'bacteria' => $mapQuery->sampleBacteria($sample, $weekEnd),
        ]);
    }
}
