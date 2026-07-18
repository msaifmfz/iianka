<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessSchedule;
use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendar-grid aggregation for the schedule dashboard: per-day counts across
 * the schedule types, recurring cleaning-duty expansion, and adjacent
 * schedule-date navigation. Extracted from ConstructionScheduleController.
 */
class ScheduleCalendarService
{
    /**
     * @param  Collection<int, string>  $types
     * @return Collection<int, array<string, mixed>>
     */
    public function calendarDays(Carbon $calendarStart, Carbon $calendarEnd, Collection $types, ?User $assignedUser = null): Collection
    {
        /** @var Collection<string, array<string, mixed>> $days */
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
                    $day = $days->get($date, $this->emptyDay($date));

                    $day['count'] += (int) $schedule->getAttribute('schedule_count');
                    $day['construction_count'] += (int) $schedule->getAttribute('schedule_count');
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
                    $day = $days->get($date, $this->emptyDay($date));

                    $day['count'] += (int) $schedule->getAttribute('schedule_count');
                    $day['business_count'] += (int) $schedule->getAttribute('schedule_count');
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
                    $day = $days->get($date, $this->emptyDay($date));

                    $day['count'] += (int) $notice->getAttribute('schedule_count');
                    $day['internal_notice_count'] += (int) $notice->getAttribute('schedule_count');
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
                    $day = $days->get($date, $this->emptyDay($date));

                    $day['count']++;
                    $day['cleaning_duty_count']++;
                    $days->put($date, $day);
                });
        }

        return $days->sortKeys()->values();
    }

    /**
     * @return Collection<int, array{scheduled_on: string, rule: CleaningDutyRule, assigned_users: Collection<int, User>}>
     */
    public function cleaningDutyOccurrences(Carbon $startsOn, Carbon $endsOn): Collection
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

        /** @var Collection<int, array{scheduled_on: string, rule: CleaningDutyRule, assigned_users: Collection<int, User>}> $occurrences */
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

    /**
     * @param  Collection<int, string>  $types
     */
    public function previousScheduleDate(Carbon $date, Collection $types): ?string
    {
        /** @var Collection<int, string|null> $dates */
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
    public function nextScheduleDate(Carbon $date, Collection $types): ?string
    {
        /** @var Collection<int, string|null> $dates */
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

    private function adjacentCleaningDutyDate(Carbon $date, int $direction): ?string
    {
        $weekdays = CleaningDutyRule::query()
            ->where('is_active', true)
            ->pluck('weekday');

        if ($weekdays->isEmpty()) {
            return null;
        }

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
     * @return array{date: string, count: int, construction_count: int, business_count: int, internal_notice_count: int, cleaning_duty_count: int}
     */
    private function emptyDay(string $date): array
    {
        return [
            'date' => $date,
            'count' => 0,
            'construction_count' => 0,
            'business_count' => 0,
            'internal_notice_count' => 0,
            'cleaning_duty_count' => 0,
        ];
    }
}
