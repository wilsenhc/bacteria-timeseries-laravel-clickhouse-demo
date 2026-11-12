<?php

use App\Http\Controllers\BacteriaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('bacteria', [BacteriaController::class, 'index'])->name('bacteria.index');
    Route::get('bacteria/{bacteria}', [BacteriaController::class, 'show'])->name('bacteria.show');
});

require __DIR__.'/settings.php';
