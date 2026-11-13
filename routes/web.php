<?php

use App\Http\Controllers\BacteriaController;
use App\Models\Bacteria;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'bacteriaCount' => Bacteria::query()->count(),
    'featuredBacteria' => Bacteria::query()
        ->orderBy('id')
        ->limit(4)
        ->get(['id', 'name', 'short_name', 'description', 'color', 'icon']),
]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard', [
        'bacteriaCount' => Bacteria::query()->count(),
    ]))->name('dashboard');

    Route::get('bacteria', [BacteriaController::class, 'index'])->name('bacteria.index');
    Route::get('bacteria/{bacteria}', [BacteriaController::class, 'show'])->name('bacteria.show');
});

require __DIR__.'/settings.php';
