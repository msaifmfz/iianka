<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\StockCatalogValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;

class StoreStockRequest extends FormRequest
{
    use StockCatalogValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canManageStocks() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'allows_fractional_quantity' => ['required', 'boolean'],
            'aliases' => ['present', 'array'],
            'aliases.*' => ['required', 'string', 'max:255'],
            'initial_quantity' => $this->stockQuantityRules(
                required: false,
                allowsFractionalQuantity: $this->boolean('allows_fractional_quantity'),
            ),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return $this->stockCatalogAfter(null);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'initial_quantity.regex' => '初期数量は0〜999,999,999.999の範囲で、小数点以下3桁まで入力できます。',
        ];
    }
}
