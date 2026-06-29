<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\BusinessScheduleController;
use App\Http\Controllers\CleaningDutyRuleController;
use App\Http\Controllers\ConstructionScheduleController;
use App\Http\Controllers\ConstructionScheduleVoucherController;
use App\Http\Controllers\ConstructionSiteController;
use App\Http\Controllers\ConstructionSubcontractorController;
use App\Http\Controllers\InternalNoticeController;
use App\Http\Controllers\ScheduleOverviewController;
use App\Http\Controllers\ScheduleSearchController;
use App\Http\Controllers\SiteGuideFileController;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => $request->user()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');

Route::get('robots.txt', fn (): ResponseFactory|Response => response("User-agent: *\nDisallow: /\n", 200, [
    'Content-Type' => 'text/plain',
]))->name('robots');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::redirect('dashboard', 'schedule-overview')->name('dashboard');
    Route::get('schedule-search', ScheduleSearchController::class)
        ->name('schedule-search.index');
    Route::get('schedule-overview', ScheduleOverviewController::class)
        ->name('schedule-overview.index');
    Route::get('voucher-confirmations', [ConstructionScheduleVoucherController::class, 'index'])
        ->name('voucher-confirmations.index');
    Route::patch('construction-schedules/{construction_schedule}/voucher-confirmation', [ConstructionScheduleVoucherController::class, 'update'])
        ->name('construction-schedules.voucher-confirmation.update');
    Route::patch('construction-schedules/{construction_schedule}/number', [ConstructionScheduleController::class, 'updateNumber'])
        ->name('construction-schedules.number.update');
    Route::patch('business-schedules/{business_schedule}/number', [BusinessScheduleController::class, 'updateNumber'])
        ->name('business-schedules.number.update');
    Route::get('site-guide-files/{site_guide_file}', [SiteGuideFileController::class, 'show'])
        ->name('site-guide-files.show');
    Route::patch('construction-subcontractors/{construction_subcontractor}', [ConstructionSubcontractorController::class, 'update'])
        ->withTrashed()
        ->name('construction-subcontractors.update');
    Route::delete('construction-subcontractors/{construction_subcontractor}', [ConstructionSubcontractorController::class, 'destroy'])
        ->name('construction-subcontractors.destroy');
    Route::resource('construction-schedules', ConstructionScheduleController::class);
    Route::resource('business-schedules', BusinessScheduleController::class);
    Route::resource('attendance-records', AttendanceRecordController::class)->only(['index', 'store', 'destroy']);
    Route::resource('internal-notices', InternalNoticeController::class);
    Route::resource('cleaning-duty-rules', CleaningDutyRuleController::class);
    Route::resource('construction-sites', ConstructionSiteController::class)
        ->parameters(['construction-sites' => 'site_guide_file']);
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');
        Route::resource('users', AdminUserController::class)->except('show');
    });
});

require __DIR__.'/settings.php';
