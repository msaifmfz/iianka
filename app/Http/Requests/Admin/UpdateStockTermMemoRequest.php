<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\StockTermValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class UpdateStockTermMemoRequest extends FormRequest
{
    use StockTermValidationRules;

    #[Override]
    protected function prepareForValidation(): void
    {
        $memo = $this->input('memo');

        if (! is_string($memo)) {
            return;
        }

        $memo = trim($memo);

        $this->merge([
            'memo' => $memo === '' ? null : $memo,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canManageStocks() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term_starts_on' => $this->stockTermRules(),
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
            'term_starts_on' => '月度',
            'memo' => 'メモ',
        ];
    }
}
