<?php

namespace App\Http\Controllers;

use App\Models\Bacteria;
use Inertia\Inertia;
use Inertia\Response;

class BacteriaController extends Controller
{
    /**
     * List all bacteria.
     */
    public function index(): Response
    {
        return Inertia::render('bacteria/Index', [
            'bacteria' => Bacteria::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Show a single bacterium.
     */
    public function show(Bacteria $bacteria): Response
    {
        return Inertia::render('bacteria/Show', [
            'bacteria' => $bacteria,
        ]);
    }
}
