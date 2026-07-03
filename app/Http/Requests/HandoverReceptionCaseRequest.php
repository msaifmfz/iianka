<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ProvidesReceptionFieldLabels;
use App\ReceptionCaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;

class HandoverReceptionCaseRequest extends FormRequest
{
    use ProvidesReceptionFieldLabels {
        attributes as private receptionCaseFieldAttributes;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('handover', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'memo' => ['required', 'string'],
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
            'memo' => '引継ぎメモ',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->reception_case->status->canTransitionTo(ReceptionCaseStatus::Handover)) {
                    $validator->errors()->add('status', 'この受付は引継ぎできません。');
                }
            },
        ];
    }
}
