<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateConstructionScheduleRequest extends FormRequest
{
    #[\Override]
    protected function prepareForValidation(): void
    {
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
            'construction_site_id' => ['nullable', 'integer', 'exists:construction_sites,id'],
            'scheduled_on' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after_or_equal:starts_at'],
            'time_note' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'postponed', 'canceled'])],
            'meeting_place' => ['required', 'string', 'max:255'],
            'personnel' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'general_contractor' => ['nullable', 'string', 'max:255'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'navigation_address' => ['required', 'string', 'max:255'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'site_guide_file_ids' => ['nullable', 'array'],
            'site_guide_file_ids.*' => ['integer', 'exists:site_guide_files,id'],
            'guide_files' => ['nullable', 'array'],
            'guide_files.*' => [
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024),
            ],
        ];
    }
}
