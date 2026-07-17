<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesScheduleReturnTo;
use App\Http\Requests\StoreBusinessScheduleRequest;
use App\Http\Requests\UpdateBusinessScheduleNumberRequest;
use App\Http\Requests\UpdateBusinessScheduleRequest;
use App\Models\AttendanceRecord;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\GeneralContractor;
use App\Models\InternalNotice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessScheduleController extends Controller
{
    use HandlesScheduleReturnTo;

    /**
     * @var list<string>
     */
    private const array DEFAULT_CONTENT_OPTIONS = [
        '見積もり作成',
        '単価記入',
        '安全書類作成',
        '施行要領書作成',
        '作業日報作成（週末）',
        '作業日報作成（月末）',
        '外回り（東)',
        '外回り（西)',
        '外回り（南)',
        '外回り（北)',
        '外回り（県内)',
        '外回り（県外)',
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('construction-schedules.index', [
            'type' => 'business',
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canManageContent() === true, 403);

        return Inertia::render('business-schedules/form', [
            'schedule' => null,
            'returnTo' => $this->returnTo($request),
            ...$this->initialFormValues($request),
            ...$this->formOptions(null),
        ]);
    }

    public function store(StoreBusinessScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $schedule = DB::transaction(function () use ($request, $validated): BusinessSchedule {
            $schedule = BusinessSchedule::create($this->scheduleAttributes($validated));

            $schedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
            $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

            return $schedule;
        });

        $this->auditSuccess('business_schedules.created', 'A business schedule was created.', $schedule, [
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
        ]);

        $returnTo = $this->returnTo($request);
        $this->flashToast('業務予定を作成しました。', resource: [
            'type' => 'business_schedule',
            'id' => $schedule->id,
            'action' => 'created',
            'label' => $schedule->location,
        ]);

        if ($returnTo !== null) {
            return redirect($returnTo);
        }

        return redirect()
            ->route('construction-schedules.index', [
                'range' => 'today',
                'date' => $schedule->scheduled_on->toDateString(),
                'type' => 'business',
            ]);
    }

    public function show(Request $request, BusinessSchedule $businessSchedule): Response
    {
        $businessSchedule->load('assignedUsers:id,name,email');

        return Inertia::render('business-schedules/show', [
            'schedule' => $this->schedulePayload(collect([$businessSchedule]))->first(),
            'canManage' => request()->user()?->canManageContent() === true,
            'returnTo' => $this->returnTo($request),
        ]);
    }

    public function edit(Request $request, BusinessSchedule $businessSchedule): Response
    {
        abort_unless($request->user()?->canManageContent() === true, 403);

        $businessSchedule->load('assignedUsers:id,name,email');

        return Inertia::render('business-schedules/form', [
            'schedule' => $this->schedulePayload(collect([$businessSchedule]))->first(),
            'returnTo' => $this->returnTo($request),
            ...$this->formOptions($businessSchedule),
        ]);
    }

    public function update(UpdateBusinessScheduleRequest $request, BusinessSchedule $businessSchedule): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $businessSchedule): void {
            $businessSchedule->update($this->scheduleAttributes($validated));
            $businessSchedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
            $this->rememberGeneralContractor($validated['general_contractor'] ?? null);
        });

        $this->auditSuccess('business_schedules.updated', 'A business schedule was updated.', $businessSchedule, [
            'changed' => array_values(array_diff(array_keys($businessSchedule->getChanges()), ['updated_at'])),
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
        ]);

        $returnTo = $this->returnTo($request);
        $this->flashToast('業務予定を修正しました。', resource: [
            'type' => 'business_schedule',
            'id' => $businessSchedule->id,
            'action' => 'updated',
            'label' => $businessSchedule->location,
        ]);

        if ($returnTo !== null) {
            return redirect($returnTo);
        }

        return redirect()
            ->route('business-schedules.show', $businessSchedule);
    }

    public function updateNumber(
        UpdateBusinessScheduleNumberRequest $request,
        BusinessSchedule $businessSchedule,
    ): RedirectResponse {
        $businessSchedule->update([
            'schedule_number' => $request->validated('schedule_number'),
        ]);

        $this->auditSuccess('business_schedules.number_updated', 'A business schedule number was updated.', $businessSchedule, [
            'schedule_number' => $businessSchedule->schedule_number,
        ]);

        $this->flashToast('業務予定の番号を更新しました。', resource: [
            'type' => 'business_schedule',
            'id' => $businessSchedule->id,
            'action' => 'saved',
            'label' => $businessSchedule->location,
        ]);

        return back();
    }

    public function destroy(Request $request, BusinessSchedule $businessSchedule): RedirectResponse
    {
        abort_unless($request->user()?->canManageContent() === true, 403);

        $this->auditSuccess('business_schedules.deleted', 'A business schedule was deleted.', $businessSchedule);

        $businessSchedule->delete();

        $this->flashToast('業務予定を削除しました。');

        return redirect()
            ->route('construction-schedules.index', ['type' => 'business']);
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
                'personnel',
                'location',
                'general_contractor',
                'person_in_charge',
                'content',
                'memo',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?BusinessSchedule $ignoredSchedule): array
    {
        $selectedUserIds = $ignoredSchedule instanceof BusinessSchedule
            ? $ignoredSchedule->assignedUsers->pluck('id')
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
            'generalContractorOptions' => $this->generalContractorOptions(),
            'contentOptions' => $this->contentOptions(),
            'scheduleAvailability' => $this->scheduleAvailability($ignoredSchedule, $users->pluck('id')),
            'attendanceLeaveRecords' => $this->attendanceLeaveRecords($users->pluck('id')),
        ];
    }

    /**
     * @param  Collection<int, int>  $userIds
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
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function scheduleAvailability(?BusinessSchedule $ignoredSchedule, Collection $userIds): Collection
    {
        $constructionSchedules = ConstructionSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->toBase()
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
            ->when($ignoredSchedule instanceof BusinessSchedule, fn ($query) => $query->whereKeyNot($ignoredSchedule->id))
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->toBase()
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
            ->toBase()
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
     * @return Collection<int, string>
     */
    private function contentOptions(): Collection
    {
        return collect(self::DEFAULT_CONTENT_OPTIONS)
            ->merge(
                BusinessSchedule::query()
                    ->whereNotNull('content')
                    ->where('content', '!=', '')
                    ->distinct()
                    ->pluck('content')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, BusinessSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function schedulePayload(Collection $schedules): Collection
    {
        return $schedules->map(fn (BusinessSchedule $schedule): array => [
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
            'assigned_users' => $schedule->assignedUsers->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ])->values();
    }
}
