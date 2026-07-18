<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\GeneralContractor;
use App\Models\InternalNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared option/payload builders for the schedule-domain controllers
 * (construction/business schedules, internal notices, overview, search),
 * extracted from previously copy-pasted private controller helpers.
 */
class ScheduleFormOptionsService
{
    /**
     * Visible-worker options for form pickers; already-selected hidden users
     * stay listed so editing an existing record doesn't drop them.
     *
     * @param  Collection<int, int>  $selectedUserIds
     * @return EloquentCollection<int, User>
     */
    public function userOptions(Collection $selectedUserIds): EloquentCollection
    {
        return User::query()
            ->where(fn ($query) => $query
                ->visibleToWorkers()
                ->when($selectedUserIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $selectedUserIds))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array{id: int, user_id: int, user_name: string, work_date: string, note: string|null}>
     */
    public function attendanceLeaveRecords(Collection $userIds, ?Carbon $workDate = null): Collection
    {
        return AttendanceRecord::query()
            ->with('user:id,name,email')
            ->where('status', AttendanceRecord::STATUS_LEAVE)
            ->when($workDate instanceof Carbon, fn ($query) => $query->whereDate('work_date', $workDate->toDateString()))
            ->whereIn('user_id', $userIds)
            ->orderBy('work_date')
            ->get()
            ->toBase()
            ->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'user_id' => $record->user_id,
                'user_name' => $record->user->name,
                'work_date' => $record->work_date->toDateString(),
                'note' => $record->note,
            ])
            ->values();
    }

    /**
     * All timed schedules across the three schedule types, for the form's
     * double-booking warnings. The schedule being edited is excluded so it
     * doesn't conflict with itself.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    public function scheduleAvailability(Collection $userIds, ConstructionSchedule|BusinessSchedule|null $ignoredSchedule = null): Collection
    {
        $constructionSchedules = ConstructionSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->when($ignoredSchedule instanceof ConstructionSchedule, fn ($query) => $query->whereKeyNot($ignoredSchedule->id))
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->toBase()
            ->map(fn (ConstructionSchedule $schedule): array => [
                'id' => $schedule->id,
                'type' => 'construction',
                'title' => $schedule->location,
                'scheduled_on' => $schedule->scheduled_on->toDateString(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'time' => $schedule->formattedTime(),
                'user_ids' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('id')->values(),
                'user_names' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('name')->values(),
            ]);

        $businessSchedules = BusinessSchedule::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->when($ignoredSchedule instanceof BusinessSchedule, fn ($query) => $query->whereKeyNot($ignoredSchedule->id))
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->toBase()
            ->map(fn (BusinessSchedule $schedule): array => [
                'id' => $schedule->id,
                'type' => 'business',
                'title' => $schedule->location,
                'scheduled_on' => $schedule->scheduled_on->toDateString(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'time' => $schedule->formattedTime(),
                'user_ids' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('id')->values(),
                'user_names' => $schedule->assignedUsers->whereIn('id', $userIds)->pluck('name')->values(),
            ]);

        $internalNotices = InternalNotice::query()
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereHas('assignedUsers')
            ->get()
            ->toBase()
            ->map(fn (InternalNotice $notice): array => [
                'id' => $notice->id,
                'type' => 'internal_notice',
                'title' => $notice->title,
                'scheduled_on' => $notice->scheduled_on->toDateString(),
                'starts_at' => $notice->starts_at,
                'ends_at' => $notice->ends_at,
                'time' => $notice->formattedTime(),
                'user_ids' => $notice->assignedUsers->whereIn('id', $userIds)->pluck('id')->values(),
                'user_names' => $notice->assignedUsers->whereIn('id', $userIds)->pluck('name')->values(),
            ]);

        return $constructionSchedules
            ->merge($businessSchedules)
            ->merge($internalNotices)
            ->filter(fn (array $schedule): bool => $schedule['user_ids']->isNotEmpty())
            ->sortBy([
                ['scheduled_on', 'asc'],
                ['starts_at', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function generalContractorOptions(): Collection
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

    public function rememberGeneralContractor(?string $generalContractor): void
    {
        if ($generalContractor === null || $generalContractor === '') {
            return;
        }

        GeneralContractor::query()->firstOrCreate([
            'name' => $generalContractor,
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{id: int, name: string, email: string|null}>
     */
    public function userPayload(Collection $users): Collection
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
