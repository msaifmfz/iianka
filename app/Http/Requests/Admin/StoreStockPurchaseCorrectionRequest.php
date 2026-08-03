<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\StockCatalogValidationRules;
use App\Concerns\StockTermValidationRules;
use App\Domain\Stock\ValueObjects\StockQuantity;
use App\Models\Stock;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StoreStockPurchaseCorrectionRequest extends FormRequest
{
    use StockCatalogValidationRules;
    use StockTermValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->canManageStocks() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stock = $this->route('stock');

        return [
            'term_starts_on' => $this->stockTermRules(),
            'quantity_to_subtract' => [
                ...$this->stockQuantityRules(
                    required: true,
                    allowsFractionalQuantity: $stock instanceof Stock && $stock->allows_fractional_quantity,
                ),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && StockQuantity::tryFromDecimal($value)?->isPositive() === false) {
                        $fail('訂正数は0より大きい値を入力してください。');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'quantity_to_subtract.regex' => '訂正数は0より大きく999,999,999.999以下で、小数点以下3桁まで入力してください。',
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
            'quantity_to_subtract' => '訂正数',
        ];
    }
}
