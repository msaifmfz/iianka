<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConstructionScheduleRequest;
use App\Http\Requests\UpdateConstructionScheduleRequest;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ConstructionSite;
use App\Models\GeneralContractor;
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
    public function index(Request $request): Response
    {
        $range = in_array($request->query('range'), ['today', 'week', 'month'], true)
            ? $request->query('range')
            : 'today';
        $type = in_array($request->query('type'), ['all', 'construction', 'business'], true)
            ? $request->query('type')
            : 'all';
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        [$startsOn, $endsOn] = $this->rangeBounds($range, $date);

        $constructionSchedules = collect();
        $businessSchedules = collect();

        if ($type !== 'business') {
            $constructionSchedules = ConstructionSchedule::query()
                ->with(['assignedUsers:id,name,email', 'site.guideFiles', 'selectedGuideFiles', 'directGuideFiles'])
                ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
                ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
                ->orderBy('scheduled_on')
                ->orderBy('starts_at')
                ->get();
        }

        if ($type !== 'construction') {
            $businessSchedules = BusinessSchedule::query()
                ->with('assignedUsers:id,name,email')
                ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
                ->whereDate('scheduled_on', '<=', $endsOn->toDateString())
                ->orderBy('scheduled_on')
                ->orderBy('starts_at')
                ->get();
        }

        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->subDays($monthStart->dayOfWeek);
        $calendarEnd = $monthEnd->copy()->addDays(6 - $monthEnd->dayOfWeek);

        $calendarDays = $this->calendarDays($calendarStart, $calendarEnd, $type);

        $user = $request->user();
        $myConstructionSchedules = $constructionSchedules->filter(
            fn (ConstructionSchedule $schedule) => $schedule->assignedUsers->contains('id', $user->id)
        );

        $teamConstructionSchedules = $constructionSchedules->reject(
            fn (ConstructionSchedule $schedule) => $schedule->assignedUsers->contains('id', $user->id)
        );

        $myBusinessSchedules = $businessSchedules->filter(
            fn (BusinessSchedule $schedule) => $schedule->assignedUsers->contains('id', $user->id)
        );

        $teamBusinessSchedules = $businessSchedules->reject(
            fn (BusinessSchedule $schedule) => $schedule->assignedUsers->contains('id', $user->id)
        );

        return Inertia::render('construction-schedules/index', [
            'filters' => [
                'range' => $range,
                'type' => $type,
                'date' => $date->toDateString(),
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
            ],
            'calendarDays' => $calendarDays,
            'scheduleNavigation' => [
                'previous_date' => $this->previousScheduleDate($date, $type),
                'next_date' => $this->nextScheduleDate($date, $type),
            ],
            'mySchedules' => $this->combinedSchedulePayload($myConstructionSchedules, $myBusinessSchedules),
            'teamSchedules' => $this->combinedSchedulePayload($teamConstructionSchedules, $teamBusinessSchedules),
            'canManage' => $user->is_admin === true,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('construction-schedules/form', [
            'schedule' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreConstructionScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $schedule = ConstructionSchedule::create($this->scheduleAttributes($validated));

        $schedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
        $schedule->selectedGuideFiles()->sync($request->input('site_guide_file_ids', []));
        $this->storeGuideFiles($schedule, $request->file('guide_files', []));
        $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

        return redirect()
            ->route('construction-schedules.index', [
                'range' => 'today',
                'date' => $schedule->scheduled_on->toDateString(),
            ])
            ->with('status', '予定を作成しました。');
    }

    public function show(ConstructionSchedule $constructionSchedule): Response
    {
        $constructionSchedule->load(['assignedUsers:id,name,email', 'site.guideFiles', 'selectedGuideFiles', 'directGuideFiles']);

        return Inertia::render('construction-schedules/show', [
            'schedule' => $this->schedulePayload(collect([$constructionSchedule]))->first(),
            'canManage' => request()->user()?->is_admin === true,
        ]);
    }

    public function edit(Request $request, ConstructionSchedule $constructionSchedule): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $constructionSchedule->load(['assignedUsers:id,name,email', 'site.guideFiles', 'selectedGuideFiles', 'directGuideFiles']);

        return Inertia::render('construction-schedules/form', [
            'schedule' => $this->schedulePayload(collect([$constructionSchedule]))->first(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateConstructionScheduleRequest $request, ConstructionSchedule $constructionSchedule): RedirectResponse
    {
        $validated = $request->validated();
        $constructionSchedule->update($this->scheduleAttributes($validated));
        $constructionSchedule->assignedUsers()->sync($request->input('assigned_user_ids', []));
        $constructionSchedule->selectedGuideFiles()->sync($request->input('site_guide_file_ids', []));
        $this->storeGuideFiles($constructionSchedule, $request->file('guide_files', []));
        $this->rememberGeneralContractor($validated['general_contractor'] ?? null);

        return redirect()
            ->route('construction-schedules.show', $constructionSchedule)
            ->with('status', '予定を更新しました。');
    }

    public function destroy(Request $request, ConstructionSchedule $constructionSchedule): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $constructionSchedule->delete();

        return redirect()
            ->route('construction-schedules.index')
            ->with('status', '予定を削除しました。');
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

    private function previousScheduleDate(Carbon $date, string $type): ?string
    {
        $dates = collect();

        if ($type !== 'business') {
            $dates->push(ConstructionSchedule::query()
                ->whereDate('scheduled_on', '<', $date->toDateString())
                ->max('scheduled_on'));
        }

        if ($type !== 'construction') {
            $dates->push(BusinessSchedule::query()
                ->whereDate('scheduled_on', '<', $date->toDateString())
                ->max('scheduled_on'));
        }

        $scheduledOn = $dates->filter()->max();

        return $scheduledOn === null ? null : Carbon::parse($scheduledOn)->toDateString();
    }

    private function nextScheduleDate(Carbon $date, string $type): ?string
    {
        $dates = collect();

        if ($type !== 'business') {
            $dates->push(ConstructionSchedule::query()
                ->whereDate('scheduled_on', '>', $date->toDateString())
                ->min('scheduled_on'));
        }

        if ($type !== 'construction') {
            $dates->push(BusinessSchedule::query()
                ->whereDate('scheduled_on', '>', $date->toDateString())
                ->min('scheduled_on'));
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
                'construction_site_id',
                'scheduled_on',
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
     * @param  array<int, UploadedFile>  $files
     */
    private function storeGuideFiles(ConstructionSchedule $schedule, array $files): void
    {
        foreach ($files as $file) {
            $schedule->directGuideFiles()->create([
                'name' => $file->getClientOriginalName(),
                'disk' => 'public',
                'path' => $file->store('site-guides', 'public'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'sites' => ConstructionSite::query()
                ->with('guideFiles')
                ->orderBy('name')
                ->get()
                ->map(fn (ConstructionSite $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'address' => $site->address,
                    'guide_files' => $this->guideFilePayload($site->guideFiles),
                ]),
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

    private function calendarDays(Carbon $calendarStart, Carbon $calendarEnd, string $type): Collection
    {
        $days = collect();

        if ($type !== 'business') {
            ConstructionSchedule::query()
                ->selectRaw('scheduled_on, count(*) as schedule_count')
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
                    ]);

                    $day['count'] += (int) $schedule->schedule_count;
                    $day['construction_count'] += (int) $schedule->schedule_count;
                    $days->put($date, $day);
                });
        }

        if ($type !== 'construction') {
            BusinessSchedule::query()
                ->selectRaw('scheduled_on, count(*) as schedule_count')
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
                    ]);

                    $day['count'] += (int) $schedule->schedule_count;
                    $day['business_count'] += (int) $schedule->schedule_count;
                    $days->put($date, $day);
                });
        }

        return $days->sortKeys()->values();
    }

    private function combinedSchedulePayload(Collection $constructionSchedules, Collection $businessSchedules): Collection
    {
        return $this->schedulePayload($constructionSchedules)
            ->merge($this->businessSchedulePayload($businessSchedules))
            ->sortBy([
                ['scheduled_on', 'asc'],
                ['starts_at', 'asc'],
                ['type', 'asc'],
            ])
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
            'site' => $schedule->site === null ? null : [
                'id' => $schedule->site->id,
                'name' => $schedule->site->name,
                'address' => $schedule->site->address,
            ],
            'assigned_users' => $schedule->assignedUsers->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
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
