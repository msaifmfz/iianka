<?php

declare(strict_types=1);

namespace App\Http\Presenters\SiteGuide;

use App\Http\Presenters\Schedule\ScheduleDetailPresenter;
use App\Models\ConstructionSchedule;
use App\Models\SiteGuideFile;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The schedules that use each site guide file, keyed by guide file id so the
 * library's cards resolve their usage list without carrying it themselves.
 *
 * Only construction schedules can reference a guide file — business schedules
 * and internal notices have no pivot at all.
 *
 * The usage list is deliberately uncapped: an admin about to rename, replace or
 * delete a guide file needs the whole picture, not a window onto it. That means
 * the result grows with schedule history, so the cost is managed rather than
 * truncated — the index keeps it behind `Inertia::optional` (fetched only once a
 * card is actually opened), the eager load is constrained to the guide files
 * being asked about, and the rows are streamed rather than collected. The client
 * shows a handful per file behind a すべて表示 toggle. Do not add a limit here
 * without changing that decision first.
 */
final readonly class GuideFileSchedulePreviews
{
    public function __construct(private ScheduleDetailPresenter $scheduleDetail) {}

    /**
     * Newest first, so the most recent use of a guide file is the first thing
     * read. Assignees are filtered to users visible to workers, mirroring the
     * schedule overview, search and stock report so hidden staff are not
     * exposed here either.
     *
     * @param  Collection<int, int>  $guideFileIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forGuideFiles(Collection $guideFileIds): array
    {
        if ($guideFileIds->isEmpty()) {
            return [];
        }

        $visibleUserIds = User::query()->visibleToWorkers()->pluck('id');
        $previews = [];

        // The eager load carries the same filter as the whereHas, so each
        // schedule arrives holding only the guide files being asked about and
        // the grouping below needs no membership test of its own.
        $schedules = ConstructionSchedule::query()
            ->whereHas('selectedGuideFiles', fn (Builder $query): Builder => $query
                ->whereIn('site_guide_files.id', $guideFileIds))
            ->with([
                'assignedUsers',
                'selectedGuideFiles' => fn ($query) => $query
                    ->select('site_guide_files.id')
                    ->whereIn('site_guide_files.id', $guideFileIds),
            ])
            ->orderByDesc('scheduled_on')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            // Streamed, so the hydrated models and the payloads built from them
            // are not both held at full size. The ordering ends in the primary
            // key, which keeps the chunking stable.
            ->lazy();

        foreach ($schedules as $schedule) {
            $payload = $this->scheduleDetail->construction($schedule, $visibleUserIds);

            foreach ($schedule->selectedGuideFiles as $guideFile) {
                $previews[$guideFile->id][] = $payload;
            }
        }

        return $previews;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forGuideFile(SiteGuideFile $guideFile): array
    {
        return $this->forGuideFiles(collect([$guideFile->id]))[$guideFile->id] ?? [];
    }
}
