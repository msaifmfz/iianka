<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAssignedUserScheduleTiming;
use App\Http\Requests\Concerns\ValidatesScheduleNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StoreBusinessScheduleRequest extends FormRequest
{
    use ValidatesAssignedUserScheduleTiming;
    use ValidatesScheduleNumber;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareScheduleNumberForValidation();

        if ($this->has('general_contractor')) {
            $generalContractor = trim((string) $this->input('general_contractor'));

            $this->merge([
                'general_contractor' => $generalContractor === '' ? null : $generalContractor,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_on' => ['required', 'date'],
            'schedule_number' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after_or_equal:starts_at'],
            'time_note' => ['nullable', 'string', 'max:255'],
            'personnel' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'general_contractor' => ['nullable', 'string', 'max:255'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'memo' => ['nullable', 'string'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...$this->assignedUserScheduleTimingAfterValidation(),
            ...$this->scheduleNumberAfterValidation(),
        ];
    }
}
