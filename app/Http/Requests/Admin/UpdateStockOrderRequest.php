<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Stock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;

class UpdateStockOrderRequest extends FormRequest
{
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
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct:strict'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! is_array($this->input('ordered_ids'))) {
                    return;
                }

                $submittedIds = collect($this->input('ordered_ids'))
                    ->map(fn (mixed $stockId): int => (int) $stockId)
                    ->sort()
                    ->values();

                $currentIds = Stock::query()
                    ->pluck('id')
                    ->sort()
                    ->values();

                if ($submittedIds->all() !== $currentIds->all()) {
                    $validator->errors()->add('ordered_ids', '現在の在庫をすべて含めてください。');
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        $validated = $this->validated();

        return array_map(intval(...), $validated['ordered_ids']);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'ordered_ids' => '表示順',
            'ordered_ids.*' => '在庫',
        ];
    }
}
