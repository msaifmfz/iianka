<?php

use App\Http\Controllers\ConstructionScheduleController;
use App\Http\Controllers\ConstructionSiteController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'construction-schedules')->name('dashboard');
    Route::resource('construction-schedules', ConstructionScheduleController::class);
    Route::resource('construction-sites', ConstructionSiteController::class);
});

require __DIR__.'/settings.php';
