<?php

namespace App\Http\Controllers;

use App\Application\Reception\ReceptionScheduleSource;
use App\Application\Stock\ScheduleStockReconciliationService;
use App\Domain\Stock\Enums\ScheduleStockSourceType;
use App\Http\Controllers\Concerns\HandlesScheduleReturnTo;
use App\Http\Requests\StoreConstructionScheduleRequest;
use App\Http\Requests\UpdateConstructionScheduleNumberRequest;
use App\Http\Requests\UpdateConstructionScheduleRequest;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ConstructionSubcontractor;
use App\Models\InternalNotice;
use App\Models\ReceptionCase;
use App\Models\ScheduleStockBalance;
use App\Models\SiteGuideFile;
use App\Models\Stock;
use App\Models\StockAlias;
use App\Models\User;
use App\Services\BusinessDate;
use App\Services\ScheduleCalendarService;
use App\Services\ScheduleFormOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ConstructionScheduleController extends Controller
{
    use HandlesScheduleReturnTo;

    public function __construct(
        private readonly ScheduleFormOptionsService $scheduleFormOptions,
        private readonly ScheduleCalendarService $scheduleCalendar,
        private readonly ReceptionScheduleSource $receptionScheduleSource,
    ) {}

    /**
     * @var array<int, string>
     */
    private const array SCHEDULE_TYPES = ['construction', 'business', 'internal_notice', 'cleaning_duty'];

    /**
     * @var array<int, string>
     */
    private const array DEFAULT_SCHEDULE_TYPES = ['construction', 'business'];

    public function index(Request $request): Response
    {
        $range = in_array($request->query('range'), ['today', 'week', 'month'], true)
            ? $request->query('range')
            : 'today';
        $types = $this->selectedScheduleTypes($request);
        $date = $this->selectedDate($request);
        [$startsOn, $endsOn] = $this->rangeBounds($range, $date);

        /** @var Collection<int, ConstructionSchedule> $constructionSchedules */
        $constructionSchedules = collect();
        /** @var Collection<int, BusinessSchedule> $businessSchedules */
        $businessSchedules = collect();
        /** @var Collection<int, InternalNotice> $internalNotices */
        $internalNotices = collect();
        $allCleaningDutyOccurrences = $this->scheduleCalendar->cleaningDutyOccurrences($startsOn, $endsOn);
        /** @var Collection<int, array<string, mixed>> $cleaningDutyOccurrences */
        $cleaningDutyOccurrences = collect();

        if ($types->contains('construction')) {
            $constructionSchedules = ConstructionSchedule::query()
                ->with(['assignedUsers:id,name,email,is_hidden_from_workers', 'subcontractors:id,name,phone', 'voucherCheckedBy:id,name,email,is_hidden_from_workers', 'selectedGuideFiles'])
                ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
                ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
                ->orderBy('scheduled_on')
                ->orderBy('starts_at')
                ->get();
        }

        if ($types->contains('business')) {
            $businessSchedules = BusinessSchedule::query()
                ->with('assignedUsers:id,name,email,is_hidden_from_workers')
                ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
                ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
                ->orderBy('scheduled_on')
                ->orderBy('starts_at')
                ->get();
        }

        if ($types->contains('internal_notice')) {
            $internalNotices = InternalNotice::query()
                ->with('assignedUsers:id,name,email,is_hidden_from_workers')
                ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
                ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
                ->orderBy('scheduled_on')
                ->orderBy('starts_at')
                ->get();
        }

        if ($types->contains('cleaning_duty')) {
            $cleaningDutyOccurrences = $allCleaningDutyOccurrences;
        }

        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->subDays($monthStart->dayOfWeek);
        $calendarEnd = $monthEnd->copy()->addDays(6 - $monthEnd->dayOfWeek);

        $user = $request->user();
        $canManage = $user->canManageContent();
        $selectedUserIds = $this->selectedUserIds($request, $user);
        $calendarDays = $this->scheduleCalendar->calendarDays($calendarStart, $calendarEnd, $types);
        $myCalendarDays = $this->scheduleCalendar->calendarDays($calendarStart, $calendarEnd, $types, $user);
        $allMyConstructionSchedules = ConstructionSchedule::query()
            ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user))
            ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
            ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
            ->get();
        $allMyBusinessSchedules = BusinessSchedule::query()
            ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user))
            ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
            ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
            ->get();
        $allMyInternalNotices = InternalNotice::query()
            ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user))
            ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
            ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
            ->get();
        $allMyCleaningDutyOccurrences = $allCleaningDutyOccurrences
            ->filter(fn (array $occurrence): bool => $occurrence['assigned_users']->contains('id', $user->id));
        $myConstructionSchedules = $constructionSchedules->filter(
            fn (ConstructionSchedule $schedule) => $schedule->assignedUsers->contains('id', $user->id)
        );

        $selectedUserConstructionSchedules = $this->filterSchedulesByAssignedUsers($constructionSchedules, $selectedUserIds);

        $myBusinessSchedules = $businessSchedules->filter(
            fn (BusinessSchedule $schedule) => $schedule->assignedUsers->contains('id', $user->id)
        );

        $selectedUserBusinessSchedules = $this->filterSchedulesByAssignedUsers($businessSchedules, $selectedUserIds);

        $myInternalNotices = $internalNotices->filter(
            fn (InternalNotice $notice) => $notice->assignedUsers->contains('id', $user->id)
        );

        $selectedUserInternalNotices = $this->filterSchedulesByAssignedUsers($internalNotices, $selectedUserIds);

        $myCleaningDutyOccurrences = $cleaningDutyOccurrences->filter(
            fn (array $occurrence): bool => $occurrence['assigned_users']->contains('id', $user->id)
        );

        $selectedUserCleaningDutyOccurrences = $cleaningDutyOccurrences->filter(
            fn (array $occurrence): bool => $occurrence['assigned_users']->pluck('id')->intersect($selectedUserIds)->isNotEmpty()
        );

        return Inertia::render('construction-schedules/index', [
            'filters' => [
                'range' => $range,
                'type' => $types->values(),
                'date' => $date->toDateString(),
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'user_ids' => $selectedUserIds->values(),
            ],
            'todayDate' => BusinessDate::today()->toDateString(),
            'calendarDays' => $calendarDays,
            'myCalendarDays' => $myCalendarDays,
            'scheduleNavigation' => [
                'previous_date' => $this->scheduleCalendar->previousScheduleDate($date, $types),
                'next_date' => $this->scheduleCalendar->nextScheduleDate($date, $types),
            ],
            'mySchedules' => $this->combinedSchedulePayload($myConstructionSchedules, $myBusinessSchedules, $myInternalNotices, $myCleaningDutyOccurrences),
            'teamSchedules' => $this->combinedSchedulePayload($constructionSchedules, $businessSchedules, $internalNotices, $cleaningDutyOccurrences),
            'selectedUserSchedules' => $this->combinedSchedulePayload($selectedUserConstructionSchedules, $selectedUserBusinessSchedules, $selectedUserInternalNotices, $selectedUserCleaningDutyOccurrences),
            'workerSummary' => [
                'assigned_count' => $allMyConstructionSchedules->count()
                    + $allMyBusinessSchedules->count()
                    + $allMyInternalNotices->count()
                    + $allMyCleaningDutyOccurrences->count(),
                'notice_count' => $allMyInternalNotices->count(),
                'pending_voucher_count' => $allMyConstructionSchedules
                    ->filter(fn (ConstructionSchedule $schedule): bool => $schedule->requiresVoucherConfirmation())
                    ->whereNull('voucher_checked_at')
                    ->count(),
                'status_change_count' => $allMyConstructionSchedules
                    ->filter(fn (ConstructionSchedule $schedule): bool => in_array(
                        $schedule->status,
                        [ConstructionSchedule::STATUS_POSTPONED, ConstructionSchedule::STATUS_CANCELED],
                        true,
                    ))
                    ->count(),
            ],
            'userOptions' => $user->canViewAllContent() ? User::query()
                ->visibleToWorkers()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_hidden_from_workers']) : [],
            'canManage' => $canManage,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage-content');

        $source = $this->receptionScheduleSource->fromRequest($request);

        if ($source instanceof ReceptionCase) {
            Gate::authorize('createSchedule', $source);
        }

        return Inertia::render('construction-schedules/form', [
            'schedule' => null,
            'returnTo' => $this->returnTo($request),
            ...$this->initialFormValues($request),
            ...$this->receptionScheduleSource->constructionFormValues($source),
            ...$this->formOptions(null),
        ]);
    }

    public function store(StoreConstructionScheduleRequest $request, ScheduleStockReconciliationService $stockReconciliation): RedirectResponse
    {
        $validated = $request->validated();
        $source = $this->receptionScheduleSource->find(
            isset($validated['reception_case_id']) ? (int) $validated['reception_case_id'] : null,
        );

        if ($source instanceof ReceptionCase) {
            Gate::authorize('createSchedule', $source);
        }

        $schedule = DB::transaction(function () use ($request, $validated, $stockReconciliation, $source): ConstructionSchedule {
            $schedule = ConstructionSchedule::create([
                ...$this->scheduleAttributes($validated),
                'reception_case_id' => $source?->id,
            ]);

            // Reconcile before any disk writes: a stock validation failure
            // rolls back the transaction but would leave stored files behind.
            $stockReconciliation->reconcile($schedule, $request->user());

            $schedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
            $this->syncSubcontractors($schedule, $validated);
            $schedule->selectedGuideFiles()->sync($request->input('site_guide_file_ids', []));
            $this->storeGuideFiles($schedule, $request->file('guide_files', []), $validated['guide_file_names'] ?? []);
            $this->scheduleFormOptions->rememberGeneralContractor($validated['general_contractor'] ?? null);

            if ($source instanceof ReceptionCase) {
                $this->receptionScheduleSource->recordCreated($source, $request->user(), $schedule);
            }

            return $schedule;
        });

        $this->auditSuccess('construction_schedules.created', 'A construction schedule was created.', $schedule, [
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
            'site_guide_file_ids' => $request->input('site_guide_file_ids', []),
            'reception_case_id' => $source?->id,
        ]);

        $this->flashToast('工事予定を作成しました。', resource: [
            'type' => 'construction_schedule',
            'id' => $schedule->id,
            'action' => 'created',
            'label' => $schedule->location,
        ]);

        return $this->redirectToReturnTo($request, route('construction-schedules.index', [
            'range' => 'today',
            'date' => $schedule->scheduled_on->toDateString(),
        ]));
    }

    public function show(Request $request, ConstructionSchedule $constructionSchedule): Response
    {
        $constructionSchedule->load([
            'assignedUsers:id,name,email,is_hidden_from_workers',
            'subcontractors:id,name,phone',
            'voucherCheckedBy:id,name,email,is_hidden_from_workers',
            'selectedGuideFiles',
            'receptionCase:id,case_number,status,company_name,site_name',
        ]);

        return Inertia::render('construction-schedules/show', [
            'schedule' => $this->schedulePayload(collect([$constructionSchedule]))->first(),
            'sourceReceptionCase' => $this->receptionScheduleSource->payload($constructionSchedule->receptionCase),
            'canManage' => request()->user()?->canManageContent() === true,
            'returnTo' => $this->returnTo($request),
            'stockUsages' => $this->stockUsages($constructionSchedule),
        ]);
    }

    /**
     * @return Collection<int, array{stock_id: int, name: string, quantity: string, is_active: bool}>
     */
    private function stockUsages(ConstructionSchedule $schedule): Collection
    {
        return ScheduleStockBalance::query()
            ->where('schedule_type', ScheduleStockSourceType::ConstructionSchedule)
            ->where('schedule_id', $schedule->id)
            ->with('stock:id,name,is_active,sort_order')
            ->get()
            ->sort(fn (ScheduleStockBalance $first, ScheduleStockBalance $second): int => $first->stock->sort_order <=> $second->stock->sort_order
                ?: $first->stock_id <=> $second->stock_id)
            ->toBase()
            ->map(fn (ScheduleStockBalance $balance): array => [
                'stock_id' => $balance->stock_id,
                'name' => $balance->stock->name,
                'quantity' => $balance->applied_quantity,
                'is_active' => $balance->stock->is_active,
            ])
            ->values();
    }

    public function edit(Request $request, ConstructionSchedule $constructionSchedule): Response
    {
        Gate::authorize('manage-content');

        $constructionSchedule->load([
            'assignedUsers:id,name,email,is_hidden_from_workers',
            'subcontractors:id,name,phone',
            'voucherCheckedBy:id,name,email,is_hidden_from_workers',
            'selectedGuideFiles',
            'receptionCase:id,case_number,status,company_name,site_name',
        ]);

        return Inertia::render('construction-schedules/form', [
            'schedule' => $this->schedulePayload(collect([$constructionSchedule]))->first(),
            'returnTo' => $this->returnTo($request),
            'sourceReceptionCase' => $this->receptionScheduleSource->payload($constructionSchedule->receptionCase),
            ...$this->formOptions($constructionSchedule),
        ]);
    }

    public function update(UpdateConstructionScheduleRequest $request, ConstructionSchedule $constructionSchedule, ScheduleStockReconciliationService $stockReconciliation): RedirectResponse
    {
        $validated = $request->validated();
        $contentVersion = $validated['content_version'] ?? null;

        DB::transaction(function () use ($request, $validated, $constructionSchedule, $stockReconciliation, $contentVersion): void {
            $constructionSchedule->update($this->scheduleAttributes($validated));

            // Reconcile before any disk writes: a stale content version or
            // other stock validation failure rolls back the transaction but
            // would leave stored files behind.
            $stockReconciliation->reconcile(
                $constructionSchedule,
                $request->user(),
                $contentVersion === null ? null : (int) $contentVersion,
            );

            $constructionSchedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
            $this->syncSubcontractors($constructionSchedule, $validated);
            $constructionSchedule->selectedGuideFiles()->sync($request->input('site_guide_file_ids', []));
            $this->storeGuideFiles($constructionSchedule, $request->file('guide_files', []), $validated['guide_file_names'] ?? []);
            $this->scheduleFormOptions->rememberGeneralContractor($validated['general_contractor'] ?? null);
        });

        $this->auditSuccess('construction_schedules.updated', 'A construction schedule was updated.', $constructionSchedule, [
            'changed' => array_values(array_diff(array_keys($constructionSchedule->getChanges()), ['updated_at'])),
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
            'site_guide_file_ids' => $request->input('site_guide_file_ids', []),
        ]);

        $this->flashToast('工事予定を修正しました。', resource: [
            'type' => 'construction_schedule',
            'id' => $constructionSchedule->id,
            'action' => 'updated',
            'label' => $constructionSchedule->location,
        ]);

        return $this->redirectToReturnTo($request, route('construction-schedules.show', $constructionSchedule));
    }

    public function updateNumber(
        UpdateConstructionScheduleNumberRequest $request,
        ConstructionSchedule $constructionSchedule,
    ): RedirectResponse {
        $constructionSchedule->update([
            'schedule_number' => $request->validated('schedule_number'),
        ]);

        $this->auditSuccess('construction_schedules.number_updated', 'A construction schedule number was updated.', $constructionSchedule, [
            'schedule_number' => $constructionSchedule->schedule_number,
        ]);

        $this->flashToast('工事予定の番号を更新しました。', resource: [
            'type' => 'construction_schedule',
            'id' => $constructionSchedule->id,
            'action' => 'saved',
            'label' => $constructionSchedule->location,
        ]);

        return back();
    }

    public function destroy(Request $request, ConstructionSchedule $constructionSchedule, ScheduleStockReconciliationService $stockReconciliation): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('construction_schedules.deleted', 'A construction schedule was deleted.', $constructionSchedule);

        DB::transaction(function () use ($request, $constructionSchedule, $stockReconciliation): void {
            $stockReconciliation->releaseFor($constructionSchedule, $request->user());
            $constructionSchedule->delete();
        });

        $this->flashToast('工事予定を削除しました。');

        return $this->redirectToReturnTo($request, route('construction-schedules.index'));
    }

    /**
     * @return Collection<int, int>
     */
    private function selectedUserIds(Request $request, User $user): Collection
    {
        if (! $user->canViewAllContent()) {
            return collect();
        }

        return collect($request->query('user_ids', []))
            ->filter(fn (mixed $userId): bool => is_numeric($userId))
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values();
    }

    private function selectedDate(Request $request): Carbon
    {
        $requestedDate = $request->query('date');

        if (! is_string($requestedDate) || $requestedDate === '') {
            return BusinessDate::today();
        }

        try {
            return Carbon::parse($requestedDate)->startOfDay();
        } catch (Throwable) {
            return BusinessDate::today();
        }
    }

    private function selectedScheduleTypes(Request $request): Collection
    {
        $type = $request->query('type');

        if ($type === 'all') {
            return collect(self::SCHEDULE_TYPES);
        }

        $types = collect(is_array($type) ? $type : [$type])
            ->filter(fn (mixed $type): bool => is_string($type))
            ->filter(fn (string $type): bool => in_array($type, self::SCHEDULE_TYPES, true))
            ->unique()
            ->values();

        return $types->isEmpty() ? collect(self::DEFAULT_SCHEDULE_TYPES) : $types;
    }

    /**
     * @template TSchedule of object
     *
     * @param  Collection<int, TSchedule>  $schedules
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, TSchedule>
     */
    private function filterSchedulesByAssignedUsers(Collection $schedules, Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return $schedules->filter(
            fn (object $schedule): bool => $schedule->assignedUsers->pluck('id')->intersect($userIds)->isNotEmpty()
        );
    }

    /**
     * @return array<int, Carbon>
     */
    private function rangeBounds(string $range, Carbon $date): array
    {
        return match ($range) {
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function scheduleAttributes(array $validated): array
    {
        return collect($validated)
            ->only([
                'scheduled_on',
                'schedule_number',
                'starts_at',
                'ends_at',
                'time_note',
                'status',
                'meeting_place',
                'personnel',
                'location',
                'site_region',
                'general_contractor',
                'person_in_charge',
                'content',
                'carry_out_note',
                'navigation_address',
            ])
            ->all();
    }

    /**
     * @param  array<int|string, UploadedFile>  $files
     * @param  array<int|string, string>  $names
     */
    private function storeGuideFiles(ConstructionSchedule $schedule, array $files, array $names): void
    {
        $guideFileIds = collect($files)
            ->map(fn (UploadedFile $file, int|string $index): int => SiteGuideFile::query()->create([
                'name' => trim($names[$index] ?? '') ?: $file->getClientOriginalName(),
                'disk' => 'local',
                'path' => $file->store('site-guides', 'local'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ])->id)
            ->all();

        $schedule->selectedGuideFiles()->syncWithoutDetaching($guideFileIds);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncSubcontractors(ConstructionSchedule $schedule, array $validated): void
    {
        $existingSubcontractorIds = collect($validated['subcontractor_ids'] ?? [])
            ->map(fn (int|string $id): int => (int) $id);

        $newSubcontractorIds = collect($validated['new_subcontractors'] ?? [])
            ->map(fn (array $subcontractor): int => ConstructionSubcontractor::query()->create([
                'name' => trim((string) $subcontractor['name']),
                'phone' => trim((string) ($subcontractor['phone'] ?? '')),
            ])->id);

        $schedule->subcontractors()->sync(
            $existingSubcontractorIds
                ->merge($newSubcontractorIds)
                ->unique()
                ->values()
                ->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?ConstructionSchedule $ignoredSchedule): array
    {
        $selectedUserIds = $ignoredSchedule instanceof ConstructionSchedule
            ? $ignoredSchedule->assignedUsers->pluck('id')
            : collect();
        $selectedSubcontractorIds = $ignoredSchedule instanceof ConstructionSchedule
            ? $ignoredSchedule->subcontractors->pluck('id')
            : collect();

        $users = $this->scheduleFormOptions->userOptions($selectedUserIds);

        return [
            'users' => $users,
            'subcontractors' => ConstructionSubcontractor::query()
                ->withTrashed()
                ->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->when($selectedSubcontractorIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $selectedSubcontractorIds))
                )
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'siteGuideFiles' => SiteGuideFile::query()
                ->orderBy('name')
                ->get()
                ->pipe(fn (Collection $files): Collection => $this->guideFilePayload($files)),
            'generalContractorOptions' => $this->scheduleFormOptions->generalContractorOptions(),
            'scheduleAvailability' => $this->scheduleFormOptions->scheduleAvailability($users->pluck('id'), $ignoredSchedule),
            'attendanceLeaveRecords' => $this->scheduleFormOptions->attendanceLeaveRecords($users->pluck('id')),
            'stockOptions' => $this->stockOptions(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, aliases: Collection<int, string>, available_quantity: string, allows_fractional_quantity: bool}>
     */
    private function stockOptions(): Collection
    {
        return Stock::query()
            ->where('is_active', true)
            ->with(['aliases' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->toBase()
            ->map(fn (Stock $stock): array => [
                'id' => $stock->id,
                'name' => $stock->name,
                'aliases' => $stock->aliases->toBase()->map(fn (StockAlias $alias): string => $alias->alias)->values(),
                'available_quantity' => $stock->current_quantity,
                'allows_fractional_quantity' => $stock->allows_fractional_quantity,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, ConstructionSchedule>  $constructionSchedules
     * @param  Collection<int, BusinessSchedule>  $businessSchedules
     * @param  Collection<int, InternalNotice>  $internalNotices
     * @param  Collection<int, array<string, mixed>>  $cleaningDutyOccurrences
     * @return Collection<int, array<string, mixed>>
     */
    private function combinedSchedulePayload(Collection $constructionSchedules, Collection $businessSchedules, Collection $internalNotices, Collection $cleaningDutyOccurrences): Collection
    {
        return $this->schedulePayload($constructionSchedules)
            ->merge($this->businessSchedulePayload($businessSchedules))
            ->merge($this->internalNoticePayload($internalNotices))
            ->merge($this->cleaningDutyPayload($cleaningDutyOccurrences))
            ->sortBy(fn (array $schedule): string => sprintf(
                '%s|%010d|%s|%s',
                $schedule['scheduled_on'],
                $schedule['schedule_number'] ?? PHP_INT_MAX,
                $schedule['starts_at'] ?? '99:99:99',
                $schedule['type']
            ))
            ->values();
    }

    /**
     * @param  Collection<int, ConstructionSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function schedulePayload(Collection $schedules): Collection
    {
        return $schedules->toBase()->map(fn (ConstructionSchedule $schedule): array => [
            'id' => $schedule->id,
            'type' => 'construction',
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'schedule_number' => $schedule->schedule_number,
            'time' => $schedule->formattedTime(),
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'time_note' => $schedule->time_note,
            'status' => $schedule->status,
            'meeting_place' => $schedule->meeting_place,
            'personnel' => $schedule->personnel,
            'location' => $schedule->location,
            'site_region' => $schedule->site_region,
            'general_contractor' => $schedule->general_contractor,
            'person_in_charge' => $schedule->person_in_charge,
            'content' => $schedule->content,
            'content_version' => $schedule->content_version,
            'carry_out_note' => $schedule->carry_out_note,
            'navigation_address' => $schedule->navigation_address,
            'google_maps_url' => $schedule->googleMapsUrl(),
            'voucher_checked' => $schedule->voucher_checked_at !== null,
            'voucher_checked_at' => $schedule->voucher_checked_at?->toJSON(),
            'voucher_checked_by' => $schedule->voucherCheckedBy === null ? null : [
                'id' => $schedule->voucherCheckedBy->id,
                'name' => $schedule->voucherCheckedBy->name,
                'email' => $schedule->voucherCheckedBy->email,
            ],
            'voucher_note' => $schedule->voucher_note,
            'assigned_users' => $this->scheduleFormOptions->userPayload($schedule->assignedUsers),
            'subcontractors' => $this->subcontractorPayload($schedule->subcontractors),
            'guide_files' => $this->guideFilePayload($schedule->allGuideFiles()),
            'selected_site_guide_file_ids' => $schedule->selectedGuideFiles->pluck('id')->values(),
        ])->values();
    }

    /**
     * @param  Collection<int, BusinessSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function businessSchedulePayload(Collection $schedules): Collection
    {
        return $schedules->toBase()->map(fn (BusinessSchedule $schedule): array => [
            'id' => $schedule->id,
            'type' => 'business',
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'schedule_number' => $schedule->schedule_number,
            'time' => $schedule->formattedTime(),
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'time_note' => $schedule->time_note,
            'personnel' => $schedule->personnel,
            'location' => $schedule->location,
            'general_contractor' => $schedule->general_contractor,
            'person_in_charge' => $schedule->person_in_charge,
            'content' => $schedule->content,
            'memo' => $schedule->memo,
            'assigned_users' => $this->scheduleFormOptions->userPayload($schedule->assignedUsers),
        ])->values();
    }

    /**
     * @param  Collection<int, InternalNotice>  $notices
     * @return Collection<int, array<string, mixed>>
     */
    private function internalNoticePayload(Collection $notices): Collection
    {
        return $notices->toBase()->map(fn (InternalNotice $notice): array => [
            'id' => $notice->id,
            'type' => 'internal_notice',
            'scheduled_on' => $notice->scheduled_on->toDateString(),
            'time' => $notice->formattedTime(),
            'starts_at' => $notice->starts_at,
            'ends_at' => $notice->ends_at,
            'time_note' => $notice->time_note,
            'title' => $notice->title,
            'location' => $notice->location,
            'content' => $notice->content,
            'memo' => $notice->memo,
            'assigned_users' => $this->scheduleFormOptions->userPayload($notice->assignedUsers),
        ])->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $occurrences
     * @return Collection<int, array<string, mixed>>
     */
    private function cleaningDutyPayload(Collection $occurrences): Collection
    {
        return $occurrences->map(fn (array $occurrence): array => [
            'id' => $occurrence['rule']->id,
            'type' => 'cleaning_duty',
            'scheduled_on' => $occurrence['scheduled_on'],
            'time' => '終日',
            'starts_at' => null,
            'ends_at' => null,
            'time_note' => '終日',
            'title' => $occurrence['rule']->label,
            'location' => $occurrence['rule']->location,
            'content' => $occurrence['rule']->notes ?? $occurrence['rule']->weekdayLabel(),
            'memo' => $occurrence['rule']->notes,
            'rule_id' => $occurrence['rule']->id,
            'weekday' => $occurrence['rule']->weekday,
            'weekday_label' => $occurrence['rule']->weekdayLabel(),
            'assigned_users' => $this->scheduleFormOptions->userPayload($occurrence['assigned_users']),
        ])->values();
    }

    /**
     * @param  Collection<int, ConstructionSubcontractor>  $subcontractors
     * @return Collection<int, array{id: int, name: string, phone: string}>
     */
    private function subcontractorPayload(Collection $subcontractors): Collection
    {
        return $subcontractors
            ->map(fn (ConstructionSubcontractor $subcontractor): array => [
                'id' => $subcontractor->id,
                'name' => $subcontractor->name,
                'phone' => $subcontractor->phone,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, SiteGuideFile>  $files
     * @return Collection<int, array<string, mixed>>
     */
    private function guideFilePayload(Collection $files): Collection
    {
        return $files->map(fn (SiteGuideFile $file): array => [
            'id' => $file->id,
            'name' => $file->name,
            'url' => $file->url(),
            'mime_type' => $file->mime_type,
        ])->values();
    }
}
