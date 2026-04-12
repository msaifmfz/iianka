<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class UpdateConstructionScheduleVoucherRequest extends FormRequest
{
    #[Override]
    protected function prepareForValidation(): void
    {
        if ($this->has('voucher_note')) {
            $voucherNote = trim((string) $this->input('voucher_note'));

            $this->merge([
                'voucher_note' => $voucherNote === '' ? null : $voucherNote,
            ]);
        }
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
            'voucher_checked' => ['required', 'boolean'],
            'voucher_note' => ['nullable', 'string'],
        ];
    }
}
