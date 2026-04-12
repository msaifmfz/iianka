<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConstructionScheduleRequest;
use App\Http\Requests\UpdateConstructionScheduleNumberRequest;
use App\Http\Requests\UpdateConstructionScheduleRequest;
use App\Models\AttendanceRecord;
use App\Models\BusinessSchedule;
use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
use App\Models\ConstructionSubcontractor;
use App\Models\GeneralContractor;
use App\Models\InternalNotice;
use App\Models\SiteGuideFile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ConstructionScheduleController extends Controller
{
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
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        [$startsOn, $endsOn] = $this->rangeBounds($range, $date);

        $constructionSchedules = collect();
        $businessSchedules = collect();
        $internalNotices = collect();
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
            $cleaningDutyOccurrences = $this->cleaningDutyOccurrences($startsOn, $endsOn);
        }

        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->subDays($monthStart->dayOfWeek);
        $calendarEnd = $monthEnd->copy()->addDays(6 - $monthEnd->dayOfWeek);

        $user = $request->user();
        $canManage = $user->is_admin === true;
        $selectedUserIds = $this->selectedUserIds($request, $user);
        $calendarDays = $this->calendarDays($calendarStart, $calendarEnd, $types);
        $myCalendarDays = $this->calendarDays($calendarStart, $calendarEnd, $types, $user);
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
        $allMyCleaningDutyOccurrences = $this->cleaningDutyOccurrences($startsOn, $endsOn)
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
            'todayDate' => today()->toDateString(),
            'calendarDays' => $calendarDays,
            'myCalendarDays' => $myCalendarDays,
            'scheduleNavigation' => [
                'previous_date' => $this->previousScheduleDate($date, $types),
                'next_date' => $this->nextScheduleDate($date, $types),
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
            'userOptions' => $canManage ? User::query()
                ->visibleToWorkers()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_hidden_from_workers']) : [],
            'canManage' => $canManage,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('construction-schedules/form', [
            'schedule' => null,
            ...$this->formOptions(null),
        ]);
    }

    public function store(StoreConstructionScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $schedule = ConstructionSchedule::create($this->scheduleAttributes($validated));

        $schedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
        $this->syncSubcontractors($schedule, $validated);
        $schedule->selectedGuideFiles()->sync($request->input('site_guide_file_ids', []));
        $this->storeGuideFiles($schedule, $request->file('guide_files', []), $validated['guide_file_names'] ?? []);
        $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

        return redirect()
            ->route('construction-schedules.index', [
                'range' => 'today',
                'date' => $schedule->scheduled_on->toDateString(),
            ])
            ->with('status', '予定を作成しました。');
    }

    public function show(Request $request, ConstructionSchedule $constructionSchedule): Response
    {
        $constructionSchedule->load(['assignedUsers:id,name,email,is_hidden_from_workers', 'subcontractors:id,name,phone', 'voucherCheckedBy:id,name,email,is_hidden_from_workers', 'selectedGuideFiles']);

        return Inertia::render('construction-schedules/show', [
            'schedule' => $this->schedulePayload(collect([$constructionSchedule]))->first(),
            'canManage' => request()->user()?->is_admin === true,
            'returnTo' => $this->returnTo($request),
        ]);
    }

    public function edit(Request $request, ConstructionSchedule $constructionSchedule): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $constructionSchedule->load(['assignedUsers:id,name,email,is_hidden_from_workers', 'subcontractors:id,name,phone', 'voucherCheckedBy:id,name,email,is_hidden_from_workers', 'selectedGuideFiles']);

        return Inertia::render('construction-schedules/form', [
            'schedule' => $this->schedulePayload(collect([$constructionSchedule]))->first(),
            ...$this->formOptions($constructionSchedule),
        ]);
    }

    public function update(UpdateConstructionScheduleRequest $request, ConstructionSchedule $constructionSchedule): RedirectResponse
    {
        $validated = $request->validated();
        $constructionSchedule->update($this->scheduleAttributes($validated));
        $constructionSchedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
        $this->syncSubcontractors($constructionSchedule, $validated);
        $constructionSchedule->selectedGuideFiles()->sync($request->input('site_guide_file_ids', []));
        $this->storeGuideFiles($constructionSchedule, $request->file('guide_files', []), $validated['guide_file_names'] ?? []);
        $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

        return redirect()
            ->route('construction-schedules.show', $constructionSchedule)
            ->with('status', '予定を更新しました。');
    }

    public function updateNumber(
        UpdateConstructionScheduleNumberRequest $request,
        ConstructionSchedule $constructionSchedule,
    ): RedirectResponse {
        $constructionSchedule->update([
            'schedule_number' => $request->validated('schedule_number'),
        ]);

        return back()->with('status', '番号を更新しました。');
    }

    public function destroy(Request $request, ConstructionSchedule $constructionSchedule): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $constructionSchedule->delete();

        return redirect()
            ->route('construction-schedules.index')
            ->with('status', '予定を削除しました。');
    }

    private function returnTo(Request $request): ?string
    {
        $returnTo = $request->query('return_to');

        if (! is_string($returnTo) || ! str_starts_with($returnTo, '/construction-schedules')) {
            return null;
        }

        return $returnTo;
    }

    /**
     * @return Collection<int, int>
     */
    private function selectedUserIds(Request $request, User $user): Collection
    {
        if ($user->is_admin !== true) {
            return collect();
        }

        return collect($request->query('user_ids', []))
            ->filter(fn (mixed $userId): bool => is_numeric($userId))
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
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
     * @param  Collection<int, string>  $types
     */
    private function previousScheduleDate(Carbon $date, Collection $types): ?string
    {
        $dates = collect();

        if ($types->contains('construction')) {
            $dates->push(ConstructionSchedule::query()
                ->whereDate('scheduled_on', '<', $date->toDateString())
                ->max('scheduled_on'));
        }

        if ($types->contains('business')) {
            $dates->push(BusinessSchedule::query()
                ->whereDate('scheduled_on', '<', $date->toDateString())
                ->max('scheduled_on'));
        }

        if ($types->contains('internal_notice')) {
            $dates->push(InternalNotice::query()
                ->whereDate('scheduled_on', '<', $date->toDateString())
                ->max('scheduled_on'));
        }

        if ($types->contains('cleaning_duty')) {
            $dates->push($this->adjacentCleaningDutyDate($date, -1));
        }

        $scheduledOn = $dates->filter()->max();

        return $scheduledOn === null ? null : Carbon::parse($scheduledOn)->toDateString();
    }

    /**
     * @param  Collection<int, string>  $types
     */
    private function nextScheduleDate(Carbon $date, Collection $types): ?string
    {
        $dates = collect();

        if ($types->contains('construction')) {
            $dates->push(ConstructionSchedule::query()
                ->whereDate('scheduled_on', '>', $date->toDateString())
                ->min('scheduled_on'));
        }

        if ($types->contains('business')) {
            $dates->push(BusinessSchedule::query()
                ->whereDate('scheduled_on', '>', $date->toDateString())
                ->min('scheduled_on'));
        }

        if ($types->contains('internal_notice')) {
            $dates->push(InternalNotice::query()
                ->whereDate('scheduled_on', '>', $date->toDateString())
                ->min('scheduled_on'));
        }

        if ($types->contains('cleaning_duty')) {
            $dates->push($this->adjacentCleaningDutyDate($date, 1));
        }

        $scheduledOn = $dates->filter()->min();

        return $scheduledOn === null ? null : Carbon::parse($scheduledOn)->toDateString();
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
                'general_contractor',
                'person_in_charge',
                'content',
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

        $users = User::query()
            ->where(fn ($query) => $query
                ->visibleToWorkers()
                ->when($selectedUserIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $selectedUserIds))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

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
            'generalContractorOptions' => $this->generalContractorOptions(),
            'scheduleAvailability' => $this->scheduleAvailability($ignoredSchedule, $users->pluck('id')),
            'attendanceLeaveRecords' => $this->attendanceLeaveRecords($users->pluck('id')),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function attendanceLeaveRecords(Collection $userIds): Collection
    {
        return AttendanceRecord::query()
            ->with('user:id,name,email')
            ->where('status', AttendanceRecord::STATUS_LEAVE)
            ->whereIn('user_id', $userIds)
            ->orderBy('work_date')
            ->get()
            ->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'user_id' => $record->user_id,
                'user_name' => $record->user->name,
                'work_date' => $record->work_date->toDateString(),
                'note' => $record->note,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function scheduleAvailability(?ConstructionSchedule $ignoredSchedule, Collection $userIds): Collection
    {
        $constructionSchedules = ConstructionSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->when($ignoredSchedule instanceof ConstructionSchedule, fn ($query) => $query->whereKeyNot($ignoredSchedule->id))
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->map(fn (ConstructionSchedule $schedule): array => [
                'id' => $schedule->id,
                'type' => 'construction',
                'title' => $schedule->location,
                'scheduled_on' => $schedule->scheduled_on->toDateString(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'time' => $schedule->formattedTime(),
                'user_ids' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('id')->values(),
                'user_names' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('name')->values(),
            ]);

        $businessSchedules = BusinessSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->map(fn (BusinessSchedule $schedule): array => [
                'id' => $schedule->id,
                'type' => 'business',
                'title' => $schedule->location,
                'scheduled_on' => $schedule->scheduled_on->toDateString(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'time' => $schedule->formattedTime(),
                'user_ids' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('id')->values(),
                'user_names' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('name')->values(),
            ]);

        $internalNotices = InternalNotice::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->map(fn (InternalNotice $notice): array => [
                'id' => $notice->id,
                'type' => 'internal_notice',
                'title' => $notice->title,
                'scheduled_on' => $notice->scheduled_on->toDateString(),
                'starts_at' => $notice->starts_at,
                'ends_at' => $notice->ends_at,
                'time' => $notice->formattedTime(),
                'user_ids' => $notice->assignedUsers->whereIn('id', $userIds)->pluck('id')->values(),
                'user_names' => $notice->assignedUsers->whereIn('id', $userIds)->pluck('name')->values(),
            ]);

        return $constructionSchedules
            ->merge($businessSchedules)
            ->merge($internalNotices)
            ->filter(fn (array $schedule): bool => $schedule['user_ids']->isNotEmpty())
            ->sortBy([
                ['scheduled_on', 'asc'],
                ['starts_at', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function generalContractorOptions(): Collection
    {
        return GeneralContractor::query()
            ->orderBy('name')
            ->pluck('name')
            ->merge(
                ConstructionSchedule::query()
                    ->whereNotNull('general_contractor')
                    ->where('general_contractor', '!=', '')
                    ->distinct()
                    ->pluck('general_contractor')
            )
            ->merge(
                BusinessSchedule::query()
                    ->whereNotNull('general_contractor')
                    ->where('general_contractor', '!=', '')
                    ->distinct()
                    ->pluck('general_contractor')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function rememberGeneralContractor(?string $generalContractor): void
    {
        if ($generalContractor === null || $generalContractor === '') {
            return;
        }

        GeneralContractor::query()->firstOrCreate([
            'name' => $generalContractor,
        ]);
    }

    /**
     * @param  Collection<int, string>  $types
     */
    private function calendarDays(Carbon $calendarStart, Carbon $calendarEnd, Collection $types, ?User $assignedUser = null): Collection
    {
        $days = collect();

        if ($types->contains('construction')) {
            ConstructionSchedule::query()
                ->selectRaw('scheduled_on, count(*) as schedule_count')
                ->when($assignedUser, fn ($query) => $query->whereHas('assignedUsers', fn ($query) => $query->whereKey($assignedUser)))
                ->whereDate('scheduled_on', '>=', $calendarStart->toDateString())
                ->whereDate('scheduled_on', '<=', $calendarEnd->toDateString())
                ->groupBy('scheduled_on')
                ->get()
                ->each(function (ConstructionSchedule $schedule) use ($days): void {
                    $date = $schedule->scheduled_on->toDateString();
                    $day = $days->get($date, [
                        'date' => $date,
                        'count' => 0,
                        'construction_count' => 0,
                        'business_count' => 0,
                        'internal_notice_count' => 0,
                        'cleaning_duty_count' => 0,
                    ]);

                    $day['count'] += (int) $schedule->schedule_count;
                    $day['construction_count'] += (int) $schedule->schedule_count;
                    $days->put($date, $day);
                });
        }

        if ($types->contains('business')) {
            BusinessSchedule::query()
                ->selectRaw('scheduled_on, count(*) as schedule_count')
                ->when($assignedUser, fn ($query) => $query->whereHas('assignedUsers', fn ($query) => $query->whereKey($assignedUser)))
                ->whereDate('scheduled_on', '>=', $calendarStart->toDateString())
                ->whereDate('scheduled_on', '<=', $calendarEnd->toDateString())
                ->groupBy('scheduled_on')
                ->get()
                ->each(function (BusinessSchedule $schedule) use ($days): void {
                    $date = $schedule->scheduled_on->toDateString();
                    $day = $days->get($date, [
                        'date' => $date,
                        'count' => 0,
                        'construction_count' => 0,
                        'business_count' => 0,
                        'internal_notice_count' => 0,
                        'cleaning_duty_count' => 0,
                    ]);

                    $day['count'] += (int) $schedule->schedule_count;
                    $day['business_count'] += (int) $schedule->schedule_count;
                    $days->put($date, $day);
                });
        }

        if ($types->contains('internal_notice')) {
            InternalNotice::query()
                ->selectRaw('scheduled_on, count(*) as schedule_count')
                ->when($assignedUser, fn ($query) => $query->whereHas('assignedUsers', fn ($query) => $query->whereKey($assignedUser)))
                ->whereDate('scheduled_on', '>=', $calendarStart->toDateString())
                ->whereDate('scheduled_on', '<=', $calendarEnd->toDateString())
                ->groupBy('scheduled_on')
                ->get()
                ->each(function (InternalNotice $notice) use ($days): void {
                    $date = $notice->scheduled_on->toDateString();
                    $day = $days->get($date, [
                        'date' => $date,
                        'count' => 0,
                        'construction_count' => 0,
                        'business_count' => 0,
                        'internal_notice_count' => 0,
                        'cleaning_duty_count' => 0,
                    ]);

                    $day['count'] += (int) $notice->schedule_count;
                    $day['internal_notice_count'] += (int) $notice->schedule_count;
                    $days->put($date, $day);
                });
        }

        if ($types->contains('cleaning_duty')) {
            $this->cleaningDutyOccurrences($calendarStart, $calendarEnd)
                ->when($assignedUser, fn (Collection $occurrences): Collection => $occurrences->filter(
                    fn (array $occurrence): bool => $occurrence['assigned_users']->contains('id', $assignedUser->id)
                ))
                ->each(function (array $occurrence) use ($days): void {
                    $date = $occurrence['scheduled_on'];
                    $day = $days->get($date, [
                        'date' => $date,
                        'count' => 0,
                        'construction_count' => 0,
                        'business_count' => 0,
                        'internal_notice_count' => 0,
                        'cleaning_duty_count' => 0,
                    ]);

                    $day['count']++;
                    $day['cleaning_duty_count']++;
                    $days->put($date, $day);
                });
        }

        return $days->sortKeys()->values();
    }

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
            'general_contractor' => $schedule->general_contractor,
            'person_in_charge' => $schedule->person_in_charge,
            'content' => $schedule->content,
            'navigation_address' => $schedule->navigation_address,
            'google_maps_url' => $schedule->googleMapsUrl(),
            'voucher_checked' => $schedule->voucher_checked_at !== null,
            'voucher_checked_at' => $schedule->voucher_checked_at?->toDateTimeString(),
            'voucher_checked_by' => $schedule->voucherCheckedBy === null ? null : [
                'id' => $schedule->voucherCheckedBy->id,
                'name' => $schedule->voucherCheckedBy->name,
                'email' => $schedule->voucherCheckedBy->email,
            ],
            'voucher_note' => $schedule->voucher_note,
            'assigned_users' => $this->userPayload($schedule->assignedUsers),
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
            'assigned_users' => $this->userPayload($schedule->assignedUsers),
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
            'assigned_users' => $this->userPayload($notice->assignedUsers),
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
            'assigned_users' => $this->userPayload($occurrence['assigned_users']),
        ])->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{id: int, name: string, email: string|null}>
     */
    private function userPayload(Collection $users): Collection
    {
        return $users
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();
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
     * @return Collection<int, array{scheduled_on: string, rule: CleaningDutyRule, assigned_users: Collection<int, User>}>
     */
    private function cleaningDutyOccurrences(Carbon $startsOn, Carbon $endsOn): Collection
    {
        $rules = CleaningDutyRule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->where('is_active', true)
            ->orderBy('weekday')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return collect();
        }

        $occurrences = collect();
        $current = $startsOn->copy()->startOfDay();

        while ($current->lte($endsOn)) {
            foreach ($rules as $rule) {
                if ($rule->weekday === $current->dayOfWeek) {
                    $occurrences->push([
                        'scheduled_on' => $current->toDateString(),
                        'rule' => $rule,
                        'assigned_users' => $rule->assignedUsers,
                    ]);
                }
            }

            $current->addDay();
        }

        return $occurrences;
    }

    private function adjacentCleaningDutyDate(Carbon $date, int $direction): ?string
    {
        $weekdays = CleaningDutyRule::query()
            ->where('is_active', true)
            ->pluck('weekday');

        if ($weekdays->isEmpty()) {
            return null;
        }

        $current = $date->copy();

        for ($offset = 1; $offset <= 7; $offset++) {
            $current = $direction > 0
                ? $date->copy()->addDays($offset)
                : $date->copy()->subDays($offset);

            if ($weekdays->contains($current->dayOfWeek)) {
                return $current->toDateString();
            }
        }

        return null;
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
