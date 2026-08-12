<?php

declare(strict_types=1);

namespace App\Http\Presenters\Stock;

use App\Application\Stock\Queries\UsageScheduleQuery;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Http\Presenters\Schedule\ScheduleDetailPresenter;
use App\Models\User;

/**
 * The schedule details behind the stock report's usage links, keyed by
 * schedule id so a row's references resolve without carrying the details
 * themselves.
 */
final readonly class UsageSchedulePreviews
{
    public function __construct(
        private UsageScheduleQuery $usageSchedules,
        private ScheduleDetailPresenter $scheduleDetail,
    ) {}

    /**
     * Previews for the given term and the next one, matching the terms the
     * report renders. Assignees are filtered to users visible to workers,
     * mirroring the schedule overview and search so hidden staff are not
     * exposed here either.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forTerm(StockTerm $selectedTerm): array
    {
        $visibleUserIds = User::query()->visibleToWorkers()->pluck('id');
        $previews = [];

        foreach ($this->usageSchedules->previewable($selectedTerm, $selectedTerm->next()) as $schedule) {
            $previews[$schedule->id] = $this->scheduleDetail->construction($schedule, $visibleUserIds);
        }

        return $previews;
    }
}
