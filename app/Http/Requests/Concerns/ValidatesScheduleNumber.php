<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use Illuminate\Database\Eloquent\Builder;
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

        if ($scheduleNumber === 0 || $scheduledOn === null) {
            return;
        }

        if (! $this->scheduleNumberExistsOnDate($scheduledOn, $scheduleNumber)) {
            return;
        }

        $validator->errors()->add(
            'schedule_number',
            '同じ日付に同じ番号の予定が既にあります。別の番号を入力してください。'
        );
    }

    private function scheduleNumberExistsOnDate(string $scheduledOn, int $scheduleNumber): bool
    {
        if ($this->numberedConstructionSchedules($scheduledOn, $scheduleNumber)->exists()) {
            return true;
        }

        return (bool) $this->numberedBusinessSchedules($scheduledOn, $scheduleNumber)->exists();
    }

    private function numberedConstructionSchedules(string $scheduledOn, int $scheduleNumber): Builder
    {
        return ConstructionSchedule::query()
            ->when(
                $this->ignoredConstructionScheduleForScheduleNumberId() !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($this->ignoredConstructionScheduleForScheduleNumberId())
            )
            ->whereDate('scheduled_on', $scheduledOn)
            ->where('schedule_number', $scheduleNumber);
    }

    private function numberedBusinessSchedules(string $scheduledOn, int $scheduleNumber): Builder
    {
        return BusinessSchedule::query()
            ->when(
                $this->ignoredBusinessScheduleForScheduleNumberId() !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($this->ignoredBusinessScheduleForScheduleNumberId())
            )
            ->whereDate('scheduled_on', $scheduledOn)
            ->where('schedule_number', $scheduleNumber);
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
