<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CleaningDutyRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class UpdateCleaningDutyRuleRequest extends FormRequest
{
    #[Override]
    protected function prepareForValidation(): void
    {
        foreach (['label', 'location', 'notes'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));

                $this->merge([$field => $value === '' ? null : $value]);
            }
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
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
            'weekday' => ['required', 'integer', Rule::in(array_keys(CleaningDutyRule::WEEKDAY_LABELS))],
            'label' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
