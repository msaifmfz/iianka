<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Stock;
use App\Models\StockAlias;
use App\Services\Stock\ScheduleContentStockParser;
use App\Services\Stock\StockNameNormalizer;
use Closure;
use Illuminate\Validation\Validator;

trait StockCatalogValidationRules
{
    /**
     * Rules for a quantity input entered as a decimal string.
     *
     * @return array<int, mixed>
     */
    protected function stockQuantityRules(bool $required, bool $allowsFractionalQuantity): array
    {
        return [
            'bail',
            $required ? 'required' : 'nullable',
            'string',
            'regex:/^\d{1,9}(\.\d{1,3})?$/',
            function (string $attribute, mixed $value, Closure $fail) use ($allowsFractionalQuantity): void {
                if (! $allowsFractionalQuantity
                    && is_string($value)
                    && ScheduleContentStockParser::decimalStringToMilliUnits($value) % 1000 !== 0) {
                    $fail('この在庫は整数のみ入力できます。');
                }
            },
        ];
    }

    /**
     * Cross-field and cross-table uniqueness of the stock name and aliases,
     * compared by normalized value (matching how the parser resolves names).
     *
     * @return list<Closure(Validator): void>
     */
    protected function stockCatalogAfter(?int $ignoreStockId): array
    {
        return [
            function (Validator $validator) use ($ignoreStockId): void {
                $normalizer = new StockNameNormalizer;
                $data = $validator->getData();

                $name = $data['name'] ?? null;
                $aliases = $data['aliases'] ?? [];

                if (! is_string($name) || ! is_array($aliases)) {
                    return;
                }

                /** @var array<string, string> $fieldsByNormalizedValue */
                $fieldsByNormalizedValue = [];

                $normalizedName = $normalizer->normalize($name);

                if ($name !== '' && $normalizedName === '') {
                    $validator->errors()->add('name', '在庫名に有効な文字を含めてください。');
                } elseif ($normalizedName !== '') {
                    $fieldsByNormalizedValue[$normalizedName] = 'name';
                }

                foreach ($aliases as $index => $alias) {
                    if (! is_string($alias)) {
                        continue;
                    }

                    $field = "aliases.{$index}";
                    $normalizedAlias = $normalizer->normalize($alias);

                    if ($normalizedAlias === '') {
                        $validator->errors()->add($field, '別名に有効な文字を含めてください。');

                        continue;
                    }

                    if (isset($fieldsByNormalizedValue[$normalizedAlias])) {
                        $validator->errors()->add($field, '在庫名または他の別名と重複しています。');

                        continue;
                    }

                    $fieldsByNormalizedValue[$normalizedAlias] = $field;
                }

                if ($fieldsByNormalizedValue === []) {
                    return;
                }

                $values = array_keys($fieldsByNormalizedValue);

                $conflicts = Stock::query()
                    ->whereIn('normalized_name', $values)
                    ->when($ignoreStockId !== null, fn ($query) => $query->where('id', '!=', $ignoreStockId))
                    ->pluck('normalized_name')
                    ->merge(
                        StockAlias::query()
                            ->whereIn('normalized_alias', $values)
                            ->when($ignoreStockId !== null, fn ($query) => $query->where('stock_id', '!=', $ignoreStockId))
                            ->pluck('normalized_alias'),
                    )
                    ->unique();

                foreach ($conflicts as $conflict) {
                    $validator->errors()->add(
                        $fieldsByNormalizedValue[$conflict],
                        'この名前は他の在庫の名前または別名として既に使用されています。',
                    );
                }
            },
        ];
    }
}
