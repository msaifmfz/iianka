<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ProvidesReceptionFieldLabels;
use App\Models\ReceptionCase;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * @property-read ReceptionCase $reception_case
 */
class AssignReceptionCaseRequest extends FormRequest
{
    use ProvidesReceptionFieldLabels {
        attributes as private receptionCaseFieldAttributes;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->reception_case) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_user_id' => [
                'required',
                'integer',
                // Reuse the same predicate as the assignee picker
                // (User::assignableAsReceptionHandler) so the two can't drift.
                Rule::exists(User::class, 'id')->where(fn ($query): mixed => User::assignableReceptionHandlerConstraint($query)),
            ],
            'memo' => ['nullable', 'string'],
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
            'memo' => '担当者へのメモ',
        ];
    }
}
