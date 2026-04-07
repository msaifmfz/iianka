<?php

use App\Http\Controllers\BusinessScheduleController;
use App\Http\Controllers\ConstructionScheduleController;
use App\Http\Controllers\ConstructionScheduleVoucherController;
use App\Http\Controllers\ConstructionSiteController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::redirect('dashboard', 'construction-schedules')->name('dashboard');
    Route::get('voucher-confirmations', [ConstructionScheduleVoucherController::class, 'index'])
        ->name('voucher-confirmations.index');
    Route::patch('construction-schedules/{construction_schedule}/voucher-confirmation', [ConstructionScheduleVoucherController::class, 'update'])
        ->name('construction-schedules.voucher-confirmation.update');
    Route::resource('construction-schedules', ConstructionScheduleController::class);
    Route::resource('business-schedules', BusinessScheduleController::class);
    Route::resource('construction-sites', ConstructionSiteController::class);
});

require __DIR__.'/settings.php';
