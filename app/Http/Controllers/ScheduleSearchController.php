<?php

namespace App\Http\Controllers;

use App\Http\Presenters\Schedule\ScheduleDetailPresenter;
use App\Http\Requests\SearchSchedulesRequest;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleSearchController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly ScheduleDetailPresenter $scheduleDetail) {}

    public function __invoke(SearchSchedulesRequest $request): Response
    {
        $filters = [
            'location' => $request->locationFilter(),
            'general_contractor' => $request->generalContractorFilter(),
            'content' => $request->contentFilter(),
            'direction' => $request->direction(),
        ];

        return Inertia::render('schedule-search/index', [
            'filters' => $filters,
            'selected' => [
                'type' => $request->selectedType(),
                'id' => $request->selectedId(),
            ],
            'results' => Inertia::scroll(fn (): LengthAwarePaginator => $this->results($filters)),
        ]);
    }

    /**
     * @param  array{location: string|null, general_contractor: string|null, content: string|null, direction: string}  $filters
     * @return LengthAwarePaginator<int, mixed>
     */
    private function results(array $filters): LengthAwarePaginator
    {
        $unionQuery = $this->scheduleSearchQuery(new ConstructionSchedule, 'construction', $filters)
            ->unionAll($this->scheduleSearchQuery(new BusinessSchedule, 'business', $filters));

        $paginator = DB::query()
            ->fromSub($unionQuery, 'schedule_search_results')
            ->orderBy('scheduled_on', $filters['direction'])
            ->orderBy('type')
            ->orderBy('id', $filters['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->hydratePaginator($paginator);
    }

    /**
     * @param  array{location: string|null, general_contractor: string|null, content: string|null, direction: string}  $filters
     */
    private function scheduleSearchQuery(ConstructionSchedule|BusinessSchedule $model, string $type, array $filters): QueryBuilder
    {
        return DB::table($model->getTable())
            ->select([
                DB::raw("'{$type}' as type"),
                'id',
                'scheduled_on',
                'schedule_number',
                'starts_at',
                'location',
                'general_contractor',
            ])
            ->when($filters['location'] !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereRaw("location like ? escape '\\'", ['%'.$this->escapeLike((string) $filters['location']).'%']))
            ->when($filters['general_contractor'] !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereRaw("general_contractor like ? escape '\\'", ['%'.$this->escapeLike((string) $filters['general_contractor']).'%']))
            ->when($filters['content'] !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereRaw("content like ? escape '\\'", ['%'.$this->escapeLike((string) $filters['content']).'%']));
    }

    /**
     * Escape LIKE wildcards so a literal % or _ in the query is matched verbatim.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return LengthAwarePaginator<int, mixed>
     */
    private function hydratePaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $rows = collect($paginator->items());
        $constructionIds = $rows
            ->where('type', 'construction')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
        $businessIds = $rows
            ->where('type', 'business')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $visibleUserIds = User::query()->visibleToWorkers()->pluck('id');

        $constructionSchedules = ConstructionSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereKey($constructionIds)
            ->get()
            ->keyBy('id');

        $businessSchedules = BusinessSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereKey($businessIds)
            ->get()
            ->keyBy('id');

        $paginator->setCollection($rows
            ->map(function (object $row) use ($constructionSchedules, $businessSchedules, $visibleUserIds): ?array {
                if ($row->type === 'construction') {
                    $schedule = $constructionSchedules->get((int) $row->id);

                    return $schedule instanceof ConstructionSchedule
                        ? $this->schedulePayload($schedule, $visibleUserIds)
                        : null;
                }

                $schedule = $businessSchedules->get((int) $row->id);

                return $schedule instanceof BusinessSchedule
                    ? $this->schedulePayload($schedule, $visibleUserIds)
                    : null;
            })
            ->filter()
            ->values());

        return $paginator;
    }

    /**
     * Build the result payload shared by both schedule types.
     *
     * Assignees are filtered to users visible to workers, mirroring the
     * schedule overview so hidden staff are not exposed through search.
     *
     * @param  Collection<int, int>  $visibleUserIds
     * @return array<string, mixed>
     */
    private function schedulePayload(ConstructionSchedule|BusinessSchedule $schedule, Collection $visibleUserIds): array
    {
        return $schedule instanceof ConstructionSchedule
            ? $this->scheduleDetail->construction($schedule, $visibleUserIds)
            : $this->scheduleDetail->business($schedule, $visibleUserIds);
    }
}
