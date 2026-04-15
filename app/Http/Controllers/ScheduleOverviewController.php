<?php

namespace App\Http\Controllers;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
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
     * @return Collection<int, array{date: string, construction_count: int, business_count: int, unconfirmed_voucher_count: int, schedule_count: int}>
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
                $day['schedule_count'] = $constructionCount + $day['business_count'];

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
                $day['schedule_count'] = $day['construction_count'] + $businessCount;

                $days->put($date, $day);
            });

        return $days->values();
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
     * @param  Collection<int, array{date: string, construction_count: int, business_count: int, unconfirmed_voucher_count: int, schedule_count: int}>  $calendarDays
     * @return array{construction_count: int, business_count: int, unconfirmed_voucher_count: int, schedule_count: int}
     */
    private function monthSummary(Collection $calendarDays, Carbon $monthStart, Carbon $monthEnd): array
    {
        return $calendarDays
            ->filter(fn (array $day): bool => $day['date'] >= $monthStart->toDateString() && $day['date'] <= $monthEnd->toDateString())
            ->reduce(
                fn (array $summary, array $day): array => [
                    'construction_count' => $summary['construction_count'] + $day['construction_count'],
                    'business_count' => $summary['business_count'] + $day['business_count'],
                    'unconfirmed_voucher_count' => $summary['unconfirmed_voucher_count'] + $day['unconfirmed_voucher_count'],
                    'schedule_count' => $summary['schedule_count'] + $day['schedule_count'],
                ],
                [
                    'construction_count' => 0,
                    'business_count' => 0,
                    'unconfirmed_voucher_count' => 0,
                    'schedule_count' => 0,
                ],
            );
    }
}
