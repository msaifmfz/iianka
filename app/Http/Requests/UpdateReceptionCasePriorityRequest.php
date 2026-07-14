<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ReceptionCase;
use App\ReceptionCasePriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * @property-read ReceptionCase $reception_case
 */
class UpdateReceptionCasePriorityRequest extends FormRequest
{
    #[Override]
    protected function prepareForValidation(): void
    {
        $priority = $this->input('priority');

        if (is_string($priority)) {
            $this->merge(['priority' => trim($priority)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('updatePriority', $this->reception_case) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(ReceptionCasePriority::class)],
        ];
    }

    public function priority(): ReceptionCasePriority
    {
        // Safe to hydrate directly: the field is validated as required + enum.
        return ReceptionCasePriority::from($this->validated('priority'));
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'priority' => '優先度',
        ];
    }
}
