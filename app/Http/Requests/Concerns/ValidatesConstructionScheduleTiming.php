<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ValidatesConstructionScheduleTiming
{
    use ValidatesAssignedUserScheduleTiming;

    protected function prepareConstructionScheduleFieldsForValidation(): void
    {
        $values = [];

        foreach (['general_contractor', 'meeting_place', 'content', 'navigation_address'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $values[$field] = $value === '' ? null : $value;
            }
        }

        if ($values !== []) {
            $this->merge($values);
        }
    }

    /**
     * @return array<int, callable>
     */
    public function constructionScheduleTimingAfterValidation(): array
    {
        return $this->assignedUserScheduleTimingAfterValidation();
    }
}
