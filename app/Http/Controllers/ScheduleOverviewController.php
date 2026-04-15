<?php

namespace App\Http\Controllers;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use App\Services\BusinessDate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ScheduleOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $date = $this->selectedDate($request);
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->subDays($monthStart->dayOfWeek);
        $calendarEnd = $monthEnd->copy()->addDays(6 - $monthEnd->dayOfWeek);
        $calendarDays = $this->calendarDays($calendarStart, $calendarEnd);

        return Inertia::render('schedule-overview/index', [
            'filters' => [
                'date' => $date->toDateString(),
            ],
            'todayDate' => BusinessDate::today()->toDateString(),
            'month' => [
                'starts_on' => $monthStart->toDateString(),
                'ends_on' => $monthEnd->toDateString(),
                'calendar_starts_on' => $calendarStart->toDateString(),
                'calendar_ends_on' => $calendarEnd->toDateString(),
            ],
            'calendarDays' => $calendarDays->values(),
            'monthSummary' => $this->monthSummary($calendarDays, $monthStart, $monthEnd),
            'canManageSchedules' => $request->user()?->canManageContent() === true,
            'selectedDayTimeline' => $this->selectedDayTimeline($date),
        ]);
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

    /**
     * @return Collection<int, array{date: string, construction_count: int, business_count: int, internal_notice_count: int, unconfirmed_voucher_count: int, schedule_count: int}>
     */
    private function calendarDays(Carbon $calendarStart, Carbon $calendarEnd): Collection
    {
        $days = collect();
        $current = $calendarStart->copy();

        while ($current->lte($calendarEnd)) {
            $date = $current->toDateString();
            $days->put($date, [
                'date' => $date,
                'construction_count' => 0,
                'business_count' => 0,
                'internal_notice_count' => 0,
                'unconfirmed_voucher_count' => 0,
                'schedule_count' => 0,
            ]);
            $current->addDay();
        }

        $this->constructionCounts($calendarStart, $calendarEnd)
            ->each(function (ConstructionSchedule $schedule) use ($days): void {
                $date = $schedule->scheduled_on->toDateString();
                $day = $days->get($date);

                if ($day === null) {
                    return;
                }

                $constructionCount = (int) $schedule->construction_count;
                $day['construction_count'] = $constructionCount;
                $day['unconfirmed_voucher_count'] = (int) $schedule->unconfirmed_voucher_count;
                $day['schedule_count'] = $this->dayScheduleCount($day);

                $days->put($date, $day);
            });

        $this->businessCounts($calendarStart, $calendarEnd)
            ->each(function (BusinessSchedule $schedule) use ($days): void {
                $date = $schedule->scheduled_on->toDateString();
                $day = $days->get($date);

                if ($day === null) {
                    return;
                }

                $businessCount = (int) $schedule->business_count;
                $day['business_count'] = $businessCount;
                $day['schedule_count'] = $this->dayScheduleCount($day);

                $days->put($date, $day);
            });

        $this->internalNoticeCounts($calendarStart, $calendarEnd)
            ->each(function (InternalNotice $notice) use ($days): void {
                $date = $notice->scheduled_on->toDateString();
                $day = $days->get($date);

                if ($day === null) {
                    return;
                }

                $day['internal_notice_count'] = (int) $notice->internal_notice_count;
                $day['schedule_count'] = $this->dayScheduleCount($day);

                $days->put($date, $day);
            });

        return $days->values();
    }

    /**
     * @param  array{construction_count: int, business_count: int, internal_notice_count: int}  $day
     */
    private function dayScheduleCount(array $day): int
    {
        return $day['construction_count'] + $day['business_count'] + $day['internal_notice_count'];
    }

    /**
     * @return EloquentCollection<int, ConstructionSchedule>
     */
    private function constructionCounts(Carbon $calendarStart, Carbon $calendarEnd): EloquentCollection
    {
        return ConstructionSchedule::query()
            ->selectRaw('scheduled_on, count(*) as construction_count, sum(case when voucher_checked_at is null then 1 else 0 end) as unconfirmed_voucher_count')
            ->whereDate('scheduled_on', '>=', $calendarStart->toDateString())
            ->whereDate('scheduled_on', '<=', $calendarEnd->toDateString())
            ->groupBy('scheduled_on')
            ->get();
    }

    /**
     * @return EloquentCollection<int, BusinessSchedule>
     */
    private function businessCounts(Carbon $calendarStart, Carbon $calendarEnd): EloquentCollection
    {
        return BusinessSchedule::query()
            ->selectRaw('scheduled_on, count(*) as business_count')
            ->whereDate('scheduled_on', '>=', $calendarStart->toDateString())
            ->whereDate('scheduled_on', '<=', $calendarEnd->toDateString())
            ->groupBy('scheduled_on')
            ->get();
    }

    /**
     * @return EloquentCollection<int, InternalNotice>
     */
    private function internalNoticeCounts(Carbon $calendarStart, Carbon $calendarEnd): EloquentCollection
    {
        return InternalNotice::query()
            ->selectRaw('scheduled_on, count(*) as internal_notice_count')
            ->whereDate('scheduled_on', '>=', $calendarStart->toDateString())
            ->whereDate('scheduled_on', '<=', $calendarEnd->toDateString())
            ->groupBy('scheduled_on')
            ->get();
    }

    /**
     * @param  Collection<int, array{date: string, construction_count: int, business_count: int, internal_notice_count: int, unconfirmed_voucher_count: int, schedule_count: int}>  $calendarDays
     * @return array{construction_count: int, business_count: int, internal_notice_count: int, unconfirmed_voucher_count: int, schedule_count: int}
     */
    private function monthSummary(Collection $calendarDays, Carbon $monthStart, Carbon $monthEnd): array
    {
        return $calendarDays
            ->filter(fn (array $day): bool => $day['date'] >= $monthStart->toDateString() && $day['date'] <= $monthEnd->toDateString())
            ->reduce(
                fn (array $summary, array $day): array => [
                    'construction_count' => $summary['construction_count'] + $day['construction_count'],
                    'business_count' => $summary['business_count'] + $day['business_count'],
                    'internal_notice_count' => $summary['internal_notice_count'] + $day['internal_notice_count'],
                    'unconfirmed_voucher_count' => $summary['unconfirmed_voucher_count'] + $day['unconfirmed_voucher_count'],
                    'schedule_count' => $summary['schedule_count'] + $day['schedule_count'],
                ],
                [
                    'construction_count' => 0,
                    'business_count' => 0,
                    'internal_notice_count' => 0,
                    'unconfirmed_voucher_count' => 0,
                    'schedule_count' => 0,
                ],
            );
    }

    /**
     * @return array{users: Collection<int, array{id: int, name: string, email: string|null}>, events: Collection<int, array<string, mixed>>}
     */
    private function selectedDayTimeline(Carbon $date): array
    {
        $users = User::query()
            ->visibleToWorkers()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $visibleUserIds = $users->pluck('id');

        return [
            'users' => $this->userPayload($users),
            'events' => $this->selectedDayEvents($date, $visibleUserIds),
        ];
    }

    /**
     * @param  Collection<int, int>  $visibleUserIds
     * @return Collection<int, array<string, mixed>>
     */
    private function selectedDayEvents(Carbon $date, Collection $visibleUserIds): Collection
    {
        $constructionSchedules = ConstructionSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereDate('scheduled_on', $date->toDateString())
            ->get([
                'id',
                'scheduled_on',
                'schedule_number',
                'starts_at',
                'ends_at',
                'time_note',
                'location',
                'content',
            ])
            ->toBase()
            ->map(fn (ConstructionSchedule $schedule): array => [
                'id' => $schedule->id,
                'type' => 'construction',
                'schedule_number' => $schedule->schedule_number,
                'title' => $schedule->location,
                'location' => $schedule->location,
                'content' => $schedule->content,
                'time' => $schedule->formattedTime(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'time_note' => $schedule->time_note,
                'assigned_users' => $this->userPayload($schedule->assignedUsers->whereIn('id', $visibleUserIds)->values()),
            ]);

        $businessSchedules = BusinessSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereDate('scheduled_on', $date->toDateString())
            ->get([
                'id',
                'scheduled_on',
                'schedule_number',
                'starts_at',
                'ends_at',
                'time_note',
                'location',
                'content',
            ])
            ->toBase()
            ->map(fn (BusinessSchedule $schedule): array => [
                'id' => $schedule->id,
                'type' => 'business',
                'schedule_number' => $schedule->schedule_number,
                'title' => $schedule->location,
                'location' => $schedule->location,
                'content' => $schedule->content,
                'time' => $schedule->formattedTime(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'time_note' => $schedule->time_note,
                'assigned_users' => $this->userPayload($schedule->assignedUsers->whereIn('id', $visibleUserIds)->values()),
            ]);

        $internalNotices = InternalNotice::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereDate('scheduled_on', $date->toDateString())
            ->get([
                'id',
                'scheduled_on',
                'starts_at',
                'ends_at',
                'time_note',
                'title',
                'location',
                'content',
            ])
            ->toBase()
            ->map(fn (InternalNotice $notice): array => [
                'id' => $notice->id,
                'type' => 'internal_notice',
                'schedule_number' => null,
                'title' => $notice->title,
                'location' => $notice->location,
                'content' => $notice->content,
                'time' => $notice->formattedTime(),
                'starts_at' => $notice->starts_at,
                'ends_at' => $notice->ends_at,
                'time_note' => $notice->time_note,
                'assigned_users' => $this->userPayload($notice->assignedUsers->whereIn('id', $visibleUserIds)->values()),
            ]);

        return $constructionSchedules
            ->merge($businessSchedules)
            ->merge($internalNotices)
            ->sortBy(fn (array $event): string => sprintf(
                '%s|%s|%s',
                $event['starts_at'] ?? '99:99:99',
                $event['ends_at'] ?? '99:99:99',
                $event['title'],
            ))
            ->values();
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
}
