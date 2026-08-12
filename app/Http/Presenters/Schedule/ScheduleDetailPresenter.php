<?php

declare(strict_types=1);

namespace App\Http\Presenters\Schedule;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use App\Services\ScheduleFormOptionsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Single builder for the schedule payload behind ScheduleDetailDialog on the
 * overview, search and stock management pages. One place for the shape means
 * the three surfaces cannot drift apart the way they had — most importantly on
 * assignee visibility, which every caller now has to state explicitly.
 */
class ScheduleDetailPresenter
{
    public function __construct(private readonly ScheduleFormOptionsService $scheduleFormOptions) {}

    /**
     * @param  Collection<int, int>  $visibleUserIds  assignees outside this set are hidden
     * @return array{
     *     id: int,
     *     type: 'construction',
     *     scheduled_on: string,
     *     schedule_number: int|null,
     *     title: string,
     *     location: string,
     *     general_contractor: string|null,
     *     content: string|null,
     *     carry_out_note: string|null,
     *     time: string,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     time_note: string|null,
     *     status: string,
     *     assigned_users: list<array{id: int, name: string, email: string|null}>,
     * }
     */
    public function construction(ConstructionSchedule $schedule, Collection $visibleUserIds): array
    {
        return [
            'id' => $schedule->id,
            'type' => 'construction',
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'schedule_number' => $schedule->schedule_number,
            'title' => $schedule->location,
            'location' => $schedule->location,
            'general_contractor' => $schedule->general_contractor,
            'content' => $schedule->content,
            'carry_out_note' => $schedule->carry_out_note,
            'time' => $schedule->formattedTime(),
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'time_note' => $schedule->time_note,
            'status' => $schedule->status,
            'assigned_users' => $this->assignedUsers($schedule->assignedUsers, $visibleUserIds),
        ];
    }

    /**
     * @param  Collection<int, int>  $visibleUserIds  assignees outside this set are hidden
     * @return array{
     *     id: int,
     *     type: 'business',
     *     scheduled_on: string,
     *     schedule_number: int|null,
     *     title: string,
     *     location: string,
     *     general_contractor: string|null,
     *     content: string|null,
     *     carry_out_note: null,
     *     time: string,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     time_note: string|null,
     *     assigned_users: list<array{id: int, name: string, email: string|null}>,
     * }
     */
    public function business(BusinessSchedule $schedule, Collection $visibleUserIds): array
    {
        return [
            'id' => $schedule->id,
            'type' => 'business',
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'schedule_number' => $schedule->schedule_number,
            'title' => $schedule->location,
            'location' => $schedule->location,
            'general_contractor' => $schedule->general_contractor,
            'content' => $schedule->content,
            'carry_out_note' => null,
            'time' => $schedule->formattedTime(),
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'time_note' => $schedule->time_note,
            'assigned_users' => $this->assignedUsers($schedule->assignedUsers, $visibleUserIds),
        ];
    }

    /**
     * @param  Collection<int, int>  $visibleUserIds  assignees outside this set are hidden
     * @return array{
     *     id: int,
     *     type: 'internal_notice',
     *     scheduled_on: string,
     *     schedule_number: null,
     *     title: string,
     *     location: string|null,
     *     content: string|null,
     *     time: string,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     time_note: string|null,
     *     assigned_users: list<array{id: int, name: string, email: string|null}>,
     * }
     */
    public function internalNotice(InternalNotice $notice, Collection $visibleUserIds): array
    {
        return [
            'id' => $notice->id,
            'type' => 'internal_notice',
            'scheduled_on' => $notice->scheduled_on->toDateString(),
            'schedule_number' => null,
            'title' => $notice->title,
            'location' => $notice->location,
            'content' => $notice->content,
            'time' => $notice->formattedTime(),
            'starts_at' => $notice->starts_at,
            'ends_at' => $notice->ends_at,
            'time_note' => $notice->time_note,
            'assigned_users' => $this->assignedUsers($notice->assignedUsers, $visibleUserIds),
        ];
    }

    /**
     * @param  EloquentCollection<int, User>  $assignedUsers
     * @param  Collection<int, int>  $visibleUserIds
     * @return list<array{id: int, name: string, email: string|null}>
     */
    private function assignedUsers(EloquentCollection $assignedUsers, Collection $visibleUserIds): array
    {
        return $this->scheduleFormOptions
            ->userPayload($assignedUsers->whereIn('id', $visibleUserIds)->values())
            ->values()
            ->all();
    }
}
