<?php

namespace App\Http\Controllers;

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

    public function __invoke(SearchSchedulesRequest $request): Response
    {
        $filters = [
            'location' => $request->locationFilter(),
            'general_contractor' => $request->generalContractorFilter(),
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
     * @param  array{location: string|null, general_contractor: string|null, direction: string}  $filters
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
     * @param  array{location: string|null, general_contractor: string|null, direction: string}  $filters
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
                ->whereRaw("general_contractor like ? escape '\\'", ['%'.$this->escapeLike((string) $filters['general_contractor']).'%']));
    }

    /**
     * Escape LIKE wildcards so a literal % or _ in the query is matched verbatim.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

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
                        ? $this->schedulePayload($schedule, 'construction', $visibleUserIds)
                        : null;
                }

                $schedule = $businessSchedules->get((int) $row->id);

                return $schedule instanceof BusinessSchedule
                    ? $this->schedulePayload($schedule, 'business', $visibleUserIds)
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
    private function schedulePayload(ConstructionSchedule|BusinessSchedule $schedule, string $type, Collection $visibleUserIds): array
    {
        return [
            'id' => $schedule->id,
            'type' => $type,
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'schedule_number' => $schedule->schedule_number,
            'time' => $schedule->formattedTime(),
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'time_note' => $schedule->time_note,
            'location' => $schedule->location,
            'general_contractor' => $schedule->general_contractor,
            'content' => $schedule->content,
            'carry_out_note' => $schedule instanceof ConstructionSchedule ? $schedule->carry_out_note : null,
            'assigned_users' => $this->userPayload(
                $schedule->assignedUsers->whereIn('id', $visibleUserIds)->values()
            ),
        ];
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
