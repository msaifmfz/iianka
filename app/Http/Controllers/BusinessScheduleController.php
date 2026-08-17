<?php

namespace App\Http\Controllers;

use App\Application\Reception\ReceptionScheduleSource;
use App\Http\Controllers\Concerns\HandlesScheduleReturnTo;
use App\Http\Requests\StoreBusinessScheduleRequest;
use App\Http\Requests\UpdateBusinessScheduleNumberRequest;
use App\Http\Requests\UpdateBusinessScheduleRequest;
use App\Models\BusinessSchedule;
use App\Models\ReceptionCase;
use App\Models\User;
use App\Services\ScheduleFormOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessScheduleController extends Controller
{
    use HandlesScheduleReturnTo;

    public function __construct(
        private readonly ScheduleFormOptionsService $scheduleFormOptions,
        private readonly ReceptionScheduleSource $receptionScheduleSource,
    ) {}

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
        Gate::authorize('manage-content');

        $source = $this->receptionScheduleSource->fromRequest($request);

        if ($source instanceof ReceptionCase) {
            Gate::authorize('createSchedule', $source);
        }

        return Inertia::render('business-schedules/form', [
            'schedule' => null,
            'returnTo' => $this->returnTo($request),
            ...$this->initialFormValues($request),
            ...$this->receptionScheduleSource->businessFormValues($source),
            ...$this->formOptions(null),
        ]);
    }

    public function store(StoreBusinessScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $source = $this->receptionScheduleSource->find(
            isset($validated['reception_case_id']) ? (int) $validated['reception_case_id'] : null,
        );

        if ($source instanceof ReceptionCase) {
            Gate::authorize('createSchedule', $source);
        }

        $schedule = DB::transaction(function () use ($request, $validated, $source): BusinessSchedule {
            $schedule = BusinessSchedule::create([
                ...$this->scheduleAttributes($validated),
                'reception_case_id' => $source?->id,
            ]);

            $schedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
            $this->scheduleFormOptions->rememberGeneralContractor($validated['general_contractor'] ?? null);

            if ($source instanceof ReceptionCase) {
                $this->receptionScheduleSource->recordCreated($source, $request->user(), $schedule);
            }

            return $schedule;
        });

        $this->auditSuccess('business_schedules.created', 'A business schedule was created.', $schedule, [
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
            'reception_case_id' => $source?->id,
        ]);

        $this->flashToast('業務予定を作成しました。', resource: [
            'type' => 'business_schedule',
            'id' => $schedule->id,
            'action' => 'created',
            'label' => $schedule->location,
        ]);

        return $this->redirectToReturnTo($request, route('construction-schedules.index', [
            'range' => 'today',
            'date' => $schedule->scheduled_on->toDateString(),
            'type' => 'business',
        ]));
    }

    public function show(Request $request, BusinessSchedule $businessSchedule): Response
    {
        $businessSchedule->load([
            'assignedUsers:id,name,email',
            'receptionCase:id,case_number,status,company_name,site_name',
        ]);

        return Inertia::render('business-schedules/show', [
            'schedule' => $this->schedulePayload(collect([$businessSchedule]))->first(),
            'sourceReceptionCase' => $this->receptionScheduleSource->payload($businessSchedule->receptionCase),
            'canManage' => request()->user()?->canManageContent() === true,
            'returnTo' => $this->returnTo($request),
        ]);
    }

    public function edit(Request $request, BusinessSchedule $businessSchedule): Response
    {
        Gate::authorize('manage-content');

        $businessSchedule->load([
            'assignedUsers:id,name,email',
            'receptionCase:id,case_number,status,company_name,site_name',
        ]);

        return Inertia::render('business-schedules/form', [
            'schedule' => $this->schedulePayload(collect([$businessSchedule]))->first(),
            'returnTo' => $this->returnTo($request),
            'sourceReceptionCase' => $this->receptionScheduleSource->payload($businessSchedule->receptionCase),
            ...$this->formOptions($businessSchedule),
        ]);
    }

    public function update(UpdateBusinessScheduleRequest $request, BusinessSchedule $businessSchedule): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $businessSchedule): void {
            $businessSchedule->update($this->scheduleAttributes($validated));
            $businessSchedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
            $this->scheduleFormOptions->rememberGeneralContractor($validated['general_contractor'] ?? null);
        });

        $this->auditSuccess('business_schedules.updated', 'A business schedule was updated.', $businessSchedule, [
            'changed' => array_values(array_diff(array_keys($businessSchedule->getChanges()), ['updated_at'])),
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
        ]);

        $this->flashToast('業務予定を修正しました。', resource: [
            'type' => 'business_schedule',
            'id' => $businessSchedule->id,
            'action' => 'updated',
            'label' => $businessSchedule->location,
        ]);

        return $this->redirectToReturnTo($request, route('business-schedules.show', $businessSchedule));
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
        Gate::authorize('manage-content');

        $this->auditSuccess('business_schedules.deleted', 'A business schedule was deleted.', $businessSchedule);

        $businessSchedule->delete();

        $this->flashToast('業務予定を削除しました。');

        return $this->redirectToReturnTo($request, route('construction-schedules.index', ['type' => 'business']));
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

        $users = $this->scheduleFormOptions->userOptions($selectedUserIds);

        return [
            'users' => $users,
            'generalContractorOptions' => $this->scheduleFormOptions->generalContractorOptions(),
            'contentOptions' => $this->contentOptions(),
            'scheduleAvailability' => $this->scheduleFormOptions->scheduleAvailability($users->pluck('id'), $ignoredSchedule),
            'attendanceLeaveRecords' => $this->scheduleFormOptions->attendanceLeaveRecords($users->pluck('id')),
        ];
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
