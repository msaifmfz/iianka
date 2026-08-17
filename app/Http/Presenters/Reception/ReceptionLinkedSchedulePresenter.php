<?php

declare(strict_types=1);

namespace App\Http\Presenters\Reception;

use App\Http\Presenters\Schedule\ScheduleDetailPresenter;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ReceptionCase;
use App\Models\User;

/**
 * The construction and business schedules created from a reception case, merged
 * into the single list the case detail page renders. Assignees are filtered to
 * users visible to workers, mirroring the schedule overview and search so
 * hidden staff are not exposed here either.
 */
final readonly class ReceptionLinkedSchedulePresenter
{
    public function __construct(private ScheduleDetailPresenter $scheduleDetail) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forCase(ReceptionCase $case): array
    {
        $visibleUserIds = User::query()->visibleToWorkers()->pluck('id');

        // ->collect() drops down to a plain collection: these map to payload
        // arrays, not models, so an Eloquent collection is the wrong container.
        return $case->constructionSchedules
            ->collect()
            ->map(fn (ConstructionSchedule $schedule): array => $this->scheduleDetail->construction($schedule, $visibleUserIds))
            ->concat($case->businessSchedules
                ->collect()
                ->map(fn (BusinessSchedule $schedule): array => $this->scheduleDetail->business($schedule, $visibleUserIds)))
            // The two relations are queried separately, so the merged list has
            // to be ordered here rather than in SQL. Newest first, with
            // same-day schedules by start time and untimed ones last.
            ->sortBy([
                ['scheduled_on', 'desc'],
                ['starts_at', 'desc'],
                ['id', 'desc'],
            ])
            ->values()
            ->all();
    }
}
