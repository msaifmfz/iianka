<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

trait ValidatesAssignedUserScheduleTiming
{
    /**
     * @return array<int, callable>
     */
    public function assignedUserScheduleTimingAfterValidation(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateAssignedUserScheduleOverlap($validator);
            },
        ];
    }

    private function validateAssignedUserScheduleOverlap(Validator $validator): void
    {
        $startsAt = $this->string('starts_at')->toString();
        $endsAt = $this->string('ends_at')->toString();
        $scheduledOn = $this->date('scheduled_on')?->toDateString();
        $assignedUserIds = $this->assignedUserIds();

        if ($startsAt === '' || $endsAt === '' || $scheduledOn === null || $assignedUserIds->isEmpty()) {
            return;
        }

        $conflict = $this->firstOverlappingSchedule($scheduledOn, $startsAt, $endsAt, $assignedUserIds);

        if ($conflict === null) {
            return;
        }

        $validator->errors()->add(
            'starts_at',
            "選択した担当ユーザーは {$conflict['time']} に {$conflict['title']} の予定があります。重ならない開始時間・終了時間を選択してください。"
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function assignedUserIds(): Collection
    {
        return collect($this->input('assigned_user_ids', []))
            ->filter(fn (mixed $userId): bool => is_numeric($userId))
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $assignedUserIds
     * @return array{title: string, time: string}|null
     */
    private function firstOverlappingSchedule(string $scheduledOn, string $startsAt, string $endsAt, Collection $assignedUserIds): ?array
    {
        $startsAt = $this->databaseTime($startsAt);
        $endsAt = $this->databaseTime($endsAt);

        $constructionSchedule = ConstructionSchedule::query()
            ->with('assignedUsers:id,name')
            ->when($this->ignoredConstructionScheduleId() !== null, fn (Builder $query): Builder => $query->whereKeyNot($this->ignoredConstructionScheduleId()))
            ->whereDate('scheduled_on', $scheduledOn)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('assignedUsers', fn (Builder $query): Builder => $query->whereIn('users.id', $assignedUserIds))
            ->orderBy('starts_at')
            ->first();

        if ($constructionSchedule !== null) {
            return [
                'title' => $constructionSchedule->location,
                'time' => $constructionSchedule->formattedTime(),
            ];
        }

        $businessSchedule = BusinessSchedule::query()
            ->with('assignedUsers:id,name')
            ->when($this->ignoredBusinessScheduleId() !== null, fn (Builder $query): Builder => $query->whereKeyNot($this->ignoredBusinessScheduleId()))
            ->whereDate('scheduled_on', $scheduledOn)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('assignedUsers', fn (Builder $query): Builder => $query->whereIn('users.id', $assignedUserIds))
            ->orderBy('starts_at')
            ->first();

        if ($businessSchedule !== null) {
            return [
                'title' => $businessSchedule->location,
                'time' => $businessSchedule->formattedTime(),
            ];
        }

        $internalNotice = InternalNotice::query()
            ->with('assignedUsers:id,name')
            ->whereDate('scheduled_on', $scheduledOn)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('assignedUsers', fn (Builder $query): Builder => $query->whereIn('users.id', $assignedUserIds))
            ->orderBy('starts_at')
            ->first();

        if ($internalNotice !== null) {
            return [
                'title' => $internalNotice->title,
                'time' => $internalNotice->formattedTime(),
            ];
        }

        return null;
    }

    private function ignoredConstructionScheduleId(): ?int
    {
        $schedule = $this->route('construction_schedule');

        return $schedule instanceof ConstructionSchedule ? $schedule->id : null;
    }

    private function ignoredBusinessScheduleId(): ?int
    {
        $schedule = $this->route('business_schedule');

        return $schedule instanceof BusinessSchedule ? $schedule->id : null;
    }

    private function databaseTime(string $time): string
    {
        return substr_count($time, ':') === 1 ? "{$time}:00" : $time;
    }
}
