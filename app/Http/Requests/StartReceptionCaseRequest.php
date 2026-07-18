<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Http\Requests\Concerns\ProvidesReceptionFieldLabels;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @property-read ReceptionCase $reception_case
 */
class StartReceptionCaseRequest extends FormRequest
{
    use ProvidesReceptionFieldLabels;

    public function authorize(): bool
    {
        return $this->user()?->can('start', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'memo' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->reception_case->assigned_user_id === null) {
                    $validator->errors()->add('assigned_user_id', '担当者を設定してください。');
                }

                if (! $this->reception_case->canTransitionTo(ReceptionCaseStatus::InProgress)) {
                    $validator->errors()->add('status', 'この受付は対応開始できません。');
                }
            },
        ];
    }
}
