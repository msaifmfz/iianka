<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesScheduleNumber;
use App\Models\BusinessSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class UpdateBusinessScheduleNumberRequest extends FormRequest
{
    use ValidatesScheduleNumber;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareScheduleNumberForValidation();

        $schedule = $this->route('business_schedule');

        if ($schedule instanceof BusinessSchedule) {
            $this->merge([
                'scheduled_on' => $schedule->scheduled_on->toDateString(),
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
            'schedule_number' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->scheduleNumberAfterValidation();
    }
}
