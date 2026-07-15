<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\StockCatalogValidationRules;
use App\Models\Stock;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Override;

class UpdateStockPurchaseRequest extends FormRequest
{
    use StockCatalogValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stock = $this->route('stock');

        return [
            'term_starts_on' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Carbon::parse($value)->day !== 21) {
                        $fail('仕入は各月度の開始日（21日）単位で入力してください。');
                    }
                },
            ],
            'quantity' => $this->stockQuantityRules(
                required: true,
                allowsFractionalQuantity: $stock instanceof Stock && $stock->allows_fractional_quantity,
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'quantity.regex' => '仕入数は0〜999,999,999.999の範囲で、小数点以下3桁まで入力できます。',
        ];
    }
}
