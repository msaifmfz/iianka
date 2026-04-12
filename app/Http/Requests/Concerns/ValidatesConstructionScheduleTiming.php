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

        if ($this->has('new_subcontractors') && is_array($this->input('new_subcontractors'))) {
            $values['new_subcontractors'] = collect($this->input('new_subcontractors'))
                ->map(fn (mixed $subcontractor): mixed => is_array($subcontractor) ? [
                    'name' => trim((string) ($subcontractor['name'] ?? '')),
                    'phone' => trim((string) ($subcontractor['phone'] ?? '')),
                ] : $subcontractor)
                ->values()
                ->all();
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
