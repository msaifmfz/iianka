<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\StockController as AdminStockController;
use App\Http\Controllers\Admin\StockPurchaseController as AdminStockPurchaseController;
use App\Http\Controllers\Admin\StockPurchaseCorrectionController as AdminStockPurchaseCorrectionController;
use App\Http\Controllers\Admin\StockTermMemoController as AdminStockTermMemoController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\BusinessScheduleController;
use App\Http\Controllers\CleaningDutyRuleController;
use App\Http\Controllers\ConstructionScheduleController;
use App\Http\Controllers\ConstructionScheduleVoucherController;
use App\Http\Controllers\ConstructionSiteController;
use App\Http\Controllers\ConstructionSubcontractorController;
use App\Http\Controllers\InternalNoticeController;
use App\Http\Controllers\ReceptionArchiveController;
use App\Http\Controllers\ReceptionCaseAssignmentController;
use App\Http\Controllers\ReceptionCaseAttachmentController;
use App\Http\Controllers\ReceptionCaseCompletionController;
use App\Http\Controllers\ReceptionCaseController;
use App\Http\Controllers\ReceptionCaseDraftController;
use App\Http\Controllers\ReceptionCaseHandoverController;
use App\Http\Controllers\ReceptionCasePriorityController;
use App\Http\Controllers\ReceptionCaseWorkMemoController;
use App\Http\Controllers\ReceptionDocumentTypeController;
use App\Http\Controllers\ReceptionDocumentTypeOrderController;
use App\Http\Controllers\ReceptionHomeController;
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
    Route::prefix('reception')->name('reception.')->group(function (): void {
        Route::get('/', [ReceptionHomeController::class, 'index'])
            ->name('home');
        Route::get('cases/create', [ReceptionCaseController::class, 'create'])
            ->name('cases.create');
        Route::post('cases/draft', [ReceptionCaseDraftController::class, 'store'])
            ->name('cases.store-draft');
        Route::patch('cases/{reception_case}/draft', [ReceptionCaseDraftController::class, 'update'])
            ->name('cases.update-draft');
        Route::post('cases/{reception_case}/submit', [ReceptionCaseController::class, 'submit'])
            ->name('cases.submit');
        Route::get('cases', [ReceptionCaseController::class, 'index'])
            ->name('cases.index');
        Route::get('cases/{reception_case}', [ReceptionCaseController::class, 'show'])
            ->name('cases.show');
        Route::post('cases/{reception_case}/attachments', [ReceptionCaseAttachmentController::class, 'store'])
            ->name('cases.attachments.store');
        Route::patch('cases/{reception_case}', [ReceptionCaseController::class, 'update'])
            ->name('cases.update');
        Route::patch('cases/{reception_case}/priority', ReceptionCasePriorityController::class)
            ->name('cases.priority.update');
        Route::patch('cases/{reception_case}/work-memo', ReceptionCaseWorkMemoController::class)
            ->name('cases.work-memo.update');
        Route::delete('cases/{reception_case}/draft', [ReceptionCaseController::class, 'destroyDraft'])
            ->name('cases.destroy-draft');
        Route::patch('cases/{reception_case}/assign', [ReceptionCaseAssignmentController::class, 'assign'])
            ->name('cases.assign');
        Route::patch('cases/{reception_case}/start', [ReceptionCaseAssignmentController::class, 'start'])
            ->name('cases.start');
        Route::post('cases/{reception_case}/handover', ReceptionCaseHandoverController::class)
            ->name('cases.handover');
        Route::post('cases/{reception_case}/complete', ReceptionCaseCompletionController::class)
            ->name('cases.complete');
        Route::get('archive', [ReceptionArchiveController::class, 'index'])
            ->name('archive.index');
        Route::get('attachments/{reception_case_attachment}', [ReceptionCaseAttachmentController::class, 'show'])
            ->name('attachments.show');
        Route::delete('attachments/{reception_case_attachment}', [ReceptionCaseAttachmentController::class, 'destroy'])
            ->name('attachments.destroy');
        Route::patch('document-types/order', ReceptionDocumentTypeOrderController::class)
            ->name('document-types.order.update');
        Route::resource('document-types', ReceptionDocumentTypeController::class)
            ->only(['index', 'store', 'update'])
            ->parameters(['document-types' => 'reception_document_type']);
    });
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');
        Route::resource('users', AdminUserController::class)->except('show');
        Route::post('stocks/{stock}/purchases', [AdminStockPurchaseController::class, 'store'])
            ->name('stocks.purchases.store');
        Route::post('stocks/{stock}/purchase-corrections', [AdminStockPurchaseCorrectionController::class, 'store'])
            ->name('stocks.purchase-corrections.store');
        Route::put('stocks/{stock}/term-memo', [AdminStockTermMemoController::class, 'update'])
            ->name('stocks.term-memo.update');
        Route::resource('stocks', AdminStockController::class)->except('show');
    });
});

require __DIR__.'/settings.php';
