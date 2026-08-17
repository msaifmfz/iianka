<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Http\Requests\Concerns\ValidatesAssignedUserScheduleTiming;
use App\Http\Requests\Concerns\ValidatesScheduleNumber;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        return $this->user()?->canManageContent() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reception_case_id' => [
                'nullable',
                'integer',
                Rule::exists(ReceptionCase::class, 'id')->whereNot('status', ReceptionCaseStatus::Draft),
            ],
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
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'reception_case_id' => '受付案件',
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
