<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

trait ValidatesScheduleNumber
{
    protected function prepareScheduleNumberForValidation(): void
    {
        if (! $this->has('schedule_number')) {
            return;
        }

        $scheduleNumber = trim((string) $this->input('schedule_number'));

        $this->merge([
            'schedule_number' => $scheduleNumber === '' ? null : $scheduleNumber,
        ]);
    }

    /**
     * @return array<int, callable>
     */
    public function scheduleNumberAfterValidation(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateScheduleNumberDoesNotOverlap($validator);
            },
        ];
    }

    private function validateScheduleNumberDoesNotOverlap(Validator $validator): void
    {
        $scheduleNumber = $this->integer('schedule_number');
        $scheduledOn = $this->date('scheduled_on')?->toDateString();
        $assignedUserIds = $this->assignedUserIdsForScheduleNumber();

        if ($scheduleNumber === 0 || $scheduledOn === null || $assignedUserIds->isEmpty()) {
            return;
        }

        if (! $this->scheduleNumberExistsOnDateForAssignedUsers($scheduledOn, $scheduleNumber, $assignedUserIds)) {
            return;
        }

        $validator->errors()->add(
            'schedule_number',
            '同じ日付に同じ番号の予定が既にあります。別の番号を入力してください。'
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function assignedUserIdsForScheduleNumber(): Collection
    {
        if ($this->has('assigned_user_ids')) {
            return collect($this->input('assigned_user_ids', []))
                ->filter(fn (mixed $userId): bool => is_numeric($userId))
                ->map(fn (mixed $userId): int => (int) $userId)
                ->unique()
                ->values();
        }

        $constructionSchedule = $this->route('construction_schedule');

        if ($constructionSchedule instanceof ConstructionSchedule) {
            return $constructionSchedule->assignedUsers()->pluck('users.id')->values();
        }

        $businessSchedule = $this->route('business_schedule');

        if ($businessSchedule instanceof BusinessSchedule) {
            return $businessSchedule->assignedUsers()->pluck('users.id')->values();
        }

        return collect();
    }

    /**
     * @param  Collection<int, int>  $assignedUserIds
     */
    private function scheduleNumberExistsOnDateForAssignedUsers(string $scheduledOn, int $scheduleNumber, Collection $assignedUserIds): bool
    {
        if ($this->numberedConstructionSchedules($scheduledOn, $scheduleNumber, $assignedUserIds)->exists()) {
            return true;
        }

        return (bool) $this->numberedBusinessSchedules($scheduledOn, $scheduleNumber, $assignedUserIds)->exists();
    }

    /**
     * @param  Collection<int, int>  $assignedUserIds
     */
    private function numberedConstructionSchedules(string $scheduledOn, int $scheduleNumber, Collection $assignedUserIds): Builder
    {
        return ConstructionSchedule::query()
            ->when(
                $this->ignoredConstructionScheduleForScheduleNumberId() !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($this->ignoredConstructionScheduleForScheduleNumberId())
            )
            ->whereDate('scheduled_on', $scheduledOn)
            ->where('schedule_number', $scheduleNumber)
            ->whereHas('assignedUsers', fn (Builder $query): Builder => $query->whereIn('users.id', $assignedUserIds));
    }

    /**
     * @param  Collection<int, int>  $assignedUserIds
     */
    private function numberedBusinessSchedules(string $scheduledOn, int $scheduleNumber, Collection $assignedUserIds): Builder
    {
        return BusinessSchedule::query()
            ->when(
                $this->ignoredBusinessScheduleForScheduleNumberId() !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($this->ignoredBusinessScheduleForScheduleNumberId())
            )
            ->whereDate('scheduled_on', $scheduledOn)
            ->where('schedule_number', $scheduleNumber)
            ->whereHas('assignedUsers', fn (Builder $query): Builder => $query->whereIn('users.id', $assignedUserIds));
    }

    private function ignoredConstructionScheduleForScheduleNumberId(): ?int
    {
        $schedule = $this->route('construction_schedule');

        return $schedule instanceof ConstructionSchedule ? $schedule->id : null;
    }

    private function ignoredBusinessScheduleForScheduleNumberId(): ?int
    {
        $schedule = $this->route('business_schedule');

        return $schedule instanceof BusinessSchedule ? $schedule->id : null;
    }
}
