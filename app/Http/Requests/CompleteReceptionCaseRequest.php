<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @property-read ReceptionCase $reception_case
 */
class CompleteReceptionCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('complete', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->reception_case->status->canTransitionTo(ReceptionCaseStatus::Completed)) {
                    $validator->errors()->add('status', 'この受付は完了できません。');
                }
            },
        ];
    }
}
