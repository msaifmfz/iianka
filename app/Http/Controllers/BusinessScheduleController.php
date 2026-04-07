<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessScheduleRequest;
use App\Http\Requests\UpdateBusinessScheduleRequest;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\GeneralContractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BusinessScheduleController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('construction-schedules.index', [
            'type' => 'business',
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('business-schedules/form', [
            'schedule' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreBusinessScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $schedule = BusinessSchedule::create($this->scheduleAttributes($validated));

        $schedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
        $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

        return redirect()
            ->route('construction-schedules.index', [
                'range' => 'today',
                'date' => $schedule->scheduled_on->toDateString(),
                'type' => 'business',
            ])
            ->with('status', '業務予定を作成しました。');
    }

    public function show(BusinessSchedule $businessSchedule): Response
    {
        $businessSchedule->load('assignedUsers:id,name,email');

        return Inertia::render('business-schedules/show', [
            'schedule' => $this->schedulePayload(collect([$businessSchedule]))->first(),
            'canManage' => request()->user()?->is_admin === true,
        ]);
    }

    public function edit(Request $request, BusinessSchedule $businessSchedule): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $businessSchedule->load('assignedUsers:id,name,email');

        return Inertia::render('business-schedules/form', [
            'schedule' => $this->schedulePayload(collect([$businessSchedule]))->first(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateBusinessScheduleRequest $request, BusinessSchedule $businessSchedule): RedirectResponse
    {
        $validated = $request->validated();
        $businessSchedule->update($this->scheduleAttributes($validated));
        $businessSchedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
        $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

        return redirect()
            ->route('business-schedules.show', $businessSchedule)
            ->with('status', '業務予定を更新しました。');
    }

    public function destroy(Request $request, BusinessSchedule $businessSchedule): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $businessSchedule->delete();

        return redirect()
            ->route('construction-schedules.index', ['type' => 'business'])
            ->with('status', '業務予定を削除しました。');
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
    private function formOptions(): array
    {
        return [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'generalContractorOptions' => $this->generalContractorOptions(),
        ];
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
     * @param  Collection<int, BusinessSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function schedulePayload(Collection $schedules): Collection
    {
        return $schedules->map(fn (BusinessSchedule $schedule) => [
            'id' => $schedule->id,
            'type' => 'business',
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
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
            'assigned_users' => $schedule->assignedUsers->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ])->values();
    }
}
