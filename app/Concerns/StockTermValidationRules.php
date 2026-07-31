<?php

declare(strict_types=1);

namespace App\Concerns;

use Closure;
use Illuminate\Support\Carbon;

trait StockTermValidationRules
{
    /**
     * @return array<int, mixed>
     */
    protected function stockTermRules(): array
    {
        return [
            'bail',
            'required',
            'date_format:Y-m-d',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                if (Carbon::parse($value)->day !== 21) {
                    $fail('月度の開始日（21日）を指定してください。');

                    return;
                }
            },
        ];
    }
}
