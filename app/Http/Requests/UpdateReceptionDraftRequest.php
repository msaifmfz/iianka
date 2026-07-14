<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesReceptionCaseFields;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * @property-read ReceptionCase $reception_case
 */
class UpdateReceptionDraftRequest extends FormRequest
{
    use ValidatesReceptionCaseFields;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareReceptionCaseFieldsForValidation();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->receptionCaseFieldRules();
    }
}
