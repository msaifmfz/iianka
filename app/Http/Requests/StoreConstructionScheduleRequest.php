<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesConstructionScheduleTiming;
use App\Http\Requests\Concerns\ValidatesGuideFileUploads;
use App\Http\Requests\Concerns\ValidatesScheduleNumber;
use App\Models\SiteGuideFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class StoreConstructionScheduleRequest extends FormRequest
{
    use ValidatesConstructionScheduleTiming;
    use ValidatesGuideFileUploads;
    use ValidatesScheduleNumber;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareConstructionScheduleFieldsForValidation();
        $this->prepareScheduleNumberForValidation();
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
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'time_note' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'postponed', 'canceled'])],
            'meeting_place' => ['nullable', 'string', 'max:255'],
            'personnel' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'general_contractor' => ['nullable', 'string', 'max:255'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'navigation_address' => ['nullable', 'string', 'max:255'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'site_guide_file_ids' => ['nullable', 'array'],
            'site_guide_file_ids.*' => ['integer', 'exists:site_guide_files,id'],
            'guide_files' => ['nullable', 'array'],
            'guide_files.*' => [
                ...$this->guideFileRules(),
            ],
            'guide_file_names' => ['nullable', 'array'],
            'guide_file_names.*' => ['nullable', 'string', 'max:255', 'distinct:strict', Rule::unique(SiteGuideFile::class, 'name')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...$this->constructionScheduleTimingAfterValidation(),
            ...$this->scheduleNumberAfterValidation(),
            ...$this->guideFileUploadAfterValidation(),
        ];
    }
}
