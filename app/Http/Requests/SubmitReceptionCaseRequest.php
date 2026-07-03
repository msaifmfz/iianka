<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesReceptionCaseFields;
use App\ReceptionCaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;

class SubmitReceptionCaseRequest extends FormRequest
{
    use ValidatesReceptionCaseFields;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareReceptionCaseFieldsForValidation();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->receptionCaseFieldRules('required');
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->reception_case->status->canTransitionTo(ReceptionCaseStatus::Received)) {
                    $validator->errors()->add('status', 'この受付は完了できません。');
                }
            },
        ];
    }
}
