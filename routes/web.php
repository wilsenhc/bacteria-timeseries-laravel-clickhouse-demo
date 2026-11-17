<?php

use App\Http\Controllers\BacteriaController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\OrganizationController;
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
    Route::get('bacteria', [BacteriaController::class, 'index'])->name('bacteria.index');
    Route::get('bacteria/{bacteria}', [BacteriaController::class, 'show'])->name('bacteria.show');

    Route::get('labs', [LabController::class, 'index'])->name('labs.index');
    Route::get('labs/create', [LabController::class, 'create'])->name('labs.create');
    Route::post('labs', [LabController::class, 'store'])->name('labs.store');
    Route::post('labs/engine', [LabController::class, 'engine'])->name('labs.engine');
    Route::match(['get', 'post'], 'labs/{lab}', [LabController::class, 'show'])->name('labs.show');
    Route::get('labs/{lab}/edit', [LabController::class, 'edit'])->name('labs.edit');
    Route::patch('labs/{lab}', [LabController::class, 'update'])->name('labs.update');
    Route::delete('labs/{lab}', [LabController::class, 'destroy'])->name('labs.destroy');

    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('organizations/select', [OrganizationController::class, 'select'])->name('organizations.select');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
});

require __DIR__.'/settings.php';
