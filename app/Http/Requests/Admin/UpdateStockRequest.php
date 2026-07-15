<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\StockCatalogValidationRules;
use App\Models\Stock;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStockRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'allows_fractional_quantity' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'aliases' => ['present', 'array'],
            'aliases.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        $stock = $this->route('stock');

        return $this->stockCatalogAfter($stock instanceof Stock ? $stock->id : null);
    }
}
