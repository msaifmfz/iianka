<?php

declare(strict_types=1);

namespace App\Domain\Quotation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class QuotationCalculator
{
    public const array CATEGORIES = ['アンカー工事', 'コア工事', 'アンカー・コア工事', '材料納品・その他'];

    public const array CONDITIONS = [
        'vacuum' => ['label' => '真空ポンプ使用', 'rate' => 10, 'provisional' => false],
        'angle' => ['label' => '角度付き施工', 'rate' => 15, 'provisional' => true],
        'deck' => ['label' => 'デッキ貫通', 'rate' => 20, 'provisional' => false],
        'high' => ['label' => '高所作業', 'rate' => 20, 'provisional' => false],
        'upward' => ['label' => '上向き施工', 'rate' => 20, 'provisional' => false],
        'pit' => ['label' => 'ピット内作業', 'rate' => 20, 'provisional' => true],
        'clean' => ['label' => 'クリーンルーム内作業', 'rate' => 25, 'provisional' => true],
    ];

    /**
     * Preserve fractional quantities and unit rates. Keep the workbook's ¥10
     * master-price rule, then settle each line and additional charge to ¥1.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, array<string, mixed>>  $catalog
     * @return array<string, mixed>
     */
    public function calculate(array $input, array $catalog): array
    {
        $lines = [];
        $subtotal = BigDecimal::zero();
        $construction = BigDecimal::zero();
        $hasConstruction = false;

        foreach ($input['items'] as $index => $item) {
            $line = $this->line($item, $catalog, $index);
            $lines[] = $line;
            $subtotal = $subtotal->plus($line['amount']);
            if ($line['construction']) {
                $hasConstruction = true;
                $construction = $construction->plus($line['amount']);
            }
        }

        $minimum = BigDecimal::of((string) ($input['minimum_charge'] ?? 10000))->toScale(0, RoundingMode::HalfUp);
        $small = ($input['small_job'] ?? false) && $hasConstruction
            ? BigDecimal::max(0, $minimum->minus($construction)) : BigDecimal::zero();
        $holiday = BigDecimal::of(($input['holiday'] ?? false) ? '20000' : '0');
        $expenses = BigDecimal::of((string) ($input['expenses'] ?? 0))->toScale(0, RoundingMode::HalfUp);
        $total = $subtotal->plus($small)->plus($holiday)->plus($expenses);
        $warnings = [];
        if ($hasConstruction && $construction->isLessThan($minimum) && ! ($input['small_job'] ?? false)) {
            $warnings[] = '施工小計が最低施工費未満です。少量施工加算の適用をご確認ください。';
        }

        return [
            'lines' => $lines,
            'rounding_policy' => 'line_half_up_yen',
            'subtotal' => (string) $subtotal,
            'construction_subtotal' => (string) $construction,
            'small_job_charge' => (string) $small,
            'holiday_charge' => (string) $holiday,
            'expenses' => (string) $expenses,
            'total' => (string) $total,
            'net' => ($input['net_enabled'] ?? false)
                ? (string) $total->multipliedBy((string) $input['net_multiplier'])->toScale(0, RoundingMode::HalfUp) : null,
            'display_mode' => count($lines) > 5 ? 'lump_sum' : ($input['display_mode'] ?? 'detail'),
            'warnings' => $warnings,
        ];
    }

    /** @param array<string, mixed> $item
     * @param  array<string, array<string, mixed>>  $catalog
     * @return array<string, mixed>
     */
    private function line(array $item, array $catalog, int $index): array
    {
        $free = $item['mode'] === 'free';
        $product = $free ? null : ($catalog[$item['catalog_id']] ?? null);
        if (! $free && $product === null) {
            throw new InvalidArgumentException('選択された品目が見つかりません。');
        }

        $rate = BigDecimal::zero();
        $fee = BigDecimal::zero();
        $notes = [];
        $needsProcessing = false;
        $base = BigDecimal::of((string) ($free ? $item['free_price'] : $product['price']));

        if (! $free) {
            $base = match ($item['adjustment'] ?? 'standard') {
                'add' => $base->plus((string) $item['adjustment_value']),
                'multiply' => $base->multipliedBy((string) $item['adjustment_value']),
                'direct' => BigDecimal::of((string) $item['adjustment_value']),
                default => $base,
            };

            foreach (self::CONDITIONS as $key => $condition) {
                if ($product['conditions'] && ! in_array($key, $product['included_conditions'], true) && ($item['conditions'][$key] ?? false)) {
                    $rate = $rate->plus((string) ($item['rates'][$key] ?? $condition['rate']));
                    $notes[] = $condition['label'];
                }
            }
            if ($product['conditions']) {
                $other = (string) ($item['other_rate'] ?? 0);
                $rate = $rate->plus($other);
                if (BigDecimal::of($other)->isPositive()) {
                    $notes[] = 'その他割増'.$other.'%';
                }
            }

            $length = $item['length'] ?? null;
            $needsProcessing = $product['processing'] && $length !== null && $length !== ''
                && ($product['stock_length'] === null || ! BigDecimal::of((string) $length)->isEqualTo($product['stock_length']));
            if ($needsProcessing) {
                $fee = match ($item['processing_mode'] ?? 'auto') {
                    'none' => BigDecimal::zero(),
                    'manual' => BigDecimal::of((string) $item['processing_fee']),
                    default => BigDecimal::of((string) $product['processing_fee']),
                };
            }
        }

        if ($base->isNegative()) {
            throw new InvalidArgumentException('調整後の単価は0円以上にしてください。');
        }

        $unitPrice = $free ? $base : $base->multipliedBy(BigDecimal::one()->plus($rate->dividedByExact(100)))
            ->dividedBy(10, 0, RoundingMode::HalfUp)->multipliedBy(10)->plus($fee);
        $name = $free ? $item['free_name'] : $product['name'];
        $spec = $free ? ($item['free_specification'] ?? '') : $product['specification'];
        if (! $free) {
            if (str_contains($name, '全ねじボルト') || str_contains($name, '異形鉄筋')) {
                $name = 'ケミカルアンカー';
            }
            if ($product['processing']) {
                preg_match('/^[DＤ]\d+/u', $spec, $matches);
                $spec = $matches[0] ?? $spec;
                if ($needsProcessing) {
                    $spec .= '（有効長='.$item['length'].'L）';
                }
            }
            if ($product['name'] === '天井アンカー') {
                $notes[] = $product['note'];
            }
        }
        if (($item['note'] ?? '') !== '' && ($item['note'] ?? null) !== null) {
            $notes[] = $item['note'];
        }

        return [
            'position' => $index + 1,
            'name' => $name,
            'specification' => $spec,
            'quantity' => (string) $item['quantity'],
            'unit' => $free ? $item['free_unit'] : $product['unit'],
            'base_price' => (string) $base,
            'surcharge_rate' => (string) $rate,
            'processing_fee' => (string) $fee,
            'needs_processing' => $needsProcessing,
            'unit_price' => (string) $unitPrice,
            'amount' => (string) $unitPrice->multipliedBy((string) $item['quantity'])->toScale(0, RoundingMode::HalfUp),
            'construction' => ! $free && $product['construction'],
            'note' => implode(' / ', $notes),
        ];
    }
}
