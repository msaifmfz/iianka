<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRequestInput;
use App\Http\Requests\Concerns\ProvidesReceptionFieldLabels;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * @property-read ReceptionCase $reception_case
 */
class UpdateReceptionCaseWorkMemoRequest extends FormRequest
{
    use NormalizesRequestInput;
    use ProvidesReceptionFieldLabels {
        attributes as private receptionCaseFieldAttributes;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'work_memo' => $this->nullableStringInput('work_memo'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('updateWorkMemo', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_memo' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            ...$this->receptionCaseFieldAttributes(),
            'work_memo' => '作業メモ',
        ];
    }
}
