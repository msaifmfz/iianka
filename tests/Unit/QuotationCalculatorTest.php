<?php

declare(strict_types=1);

use App\Domain\Quotation\QuotationCalculator;

mutates(QuotationCalculator::class);

/** @return array<string, array<string, mixed>> */
function quotationCatalogFixture(): array
{
    return array_column(json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/data/quotation-catalog.json'), true, flags: JSON_THROW_ON_ERROR), null, 'id');
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function quotationLineFixture(array $changes = []): array
{
    return array_replace([
        'mode' => 'master', 'catalog_id' => '0001', 'quantity' => '100', 'adjustment' => 'standard',
        'conditions' => [], 'rates' => [], 'length' => '', 'processing_mode' => 'auto', 'note' => '',
    ], $changes);
}

/** @param list<array<string, mixed>> $items
 * @param  array<string, mixed>  $settings
 * @return array<string, mixed>
 */
function calculateQuotationFixture(array $items, array $settings = []): array
{
    return (new QuotationCalculator)->calculate(array_replace(['items' => $items], $settings), quotationCatalogFixture());
}

test('the original sample rounds the surcharged unit price then adds cutting', function (): void {
    $result = calculateQuotationFixture([quotationLineFixture(['conditions' => ['deck' => true], 'length' => '300'])]);
    expect($result['total'])->toBe('15000');
    expect($result['lines'][0])->toMatchArray(['unit_price' => '150', 'processing_fee' => '10', 'specification' => 'D10（有効長=300L）']);
});

test('processing uses the full recorded fee only for a nonstandard length', function (string $id, string $length, string $fee): void {
    $result = calculateQuotationFixture([quotationLineFixture(['catalog_id' => $id, 'length' => $length])]);
    expect($result['lines'][0]['processing_fee'])->toBe($fee);
})->with([
    'standard D10' => ['0001', '450', '0'], 'no cut requested' => ['0017', '', '0'],
    'rebar D10' => ['0017', '300', '10'], 'rebar D16 confirmed' => ['0021', '300', '12'],
    'three digit fee D25' => ['0029', '300', '100'],
]);

test('manual cutting cannot charge a stock item or an unrelated item', function (string $id, string $length): void {
    $result = calculateQuotationFixture([quotationLineFixture(['catalog_id' => $id, 'length' => $length, 'processing_mode' => 'manual', 'processing_fee' => '999'])]);
    expect($result['lines'][0]['processing_fee'])->toBe('0');
})->with([['0001', '450'], ['0004', ''], ['0002', '300']]);

test('surcharges add and direct prices still receive rounding and manual cutting', function (): void {
    $result = calculateQuotationFixture([quotationLineFixture(['adjustment' => 'direct', 'adjustment_value' => '1000', 'conditions' => ['vacuum' => true, 'angle' => true, 'deck' => true]])]);
    expect($result['lines'][0]['unit_price'])->toBe('1450');
    $result = calculateQuotationFixture([quotationLineFixture(['adjustment' => 'direct', 'adjustment_value' => '123', 'conditions' => ['deck' => true], 'length' => '300', 'processing_mode' => 'manual', 'processing_fee' => '7'])]);
    expect($result['lines'][0]['unit_price'])->toBe('157');
});

test('half-up ten yen rounding is applied before processing', function (string $price, string $expected): void {
    $result = calculateQuotationFixture([quotationLineFixture(['adjustment' => 'direct', 'adjustment_value' => $price])]);
    expect($result['lines'][0]['unit_price'])->toBe($expected);
})->with([['144', '140'], ['145', '150'], ['145.0000', '150']]);

test('a free price and quantity stay exact while its line amount rounds to yen and ignores construction options', function (): void {
    $result = calculateQuotationFixture([quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => '123.45', 'quantity' => '2.125', 'conditions' => ['deck' => true], 'length' => '300'])], ['small_job' => true]);
    expect($result['lines'][0])->toMatchArray(['unit_price' => '123.45', 'quantity' => '2.125', 'amount' => '262']);
    expect($result['total'])->toBe('262');
    expect($result['construction_subtotal'])->toBe('0');
    expect($result['small_job_charge'])->toBe('0');
});

test('the manual minimum applies only to construction and holiday adds once', function (): void {
    $items = [quotationLineFixture(['quantity' => '50']), quotationLineFixture(['mode' => 'free', 'free_name' => '納品材料', 'free_unit' => '式', 'free_price' => '30000', 'quantity' => '1'])];
    $result = calculateQuotationFixture($items, ['small_job' => true, 'holiday' => true, 'expenses' => '2000']);
    expect($result)->toMatchArray(['subtotal' => '36000', 'construction_subtotal' => '6000', 'small_job_charge' => '4000', 'holiday_charge' => '20000', 'total' => '62000']);
    $result = calculateQuotationFixture($items);
    expect($result['total'])->toBe('36000');
    expect($result['warnings'])->toHaveCount(1);
});

test('bundled ceiling conditions cannot be charged again even with stale input', function (): void {
    $result = calculateQuotationFixture([quotationLineFixture(['catalog_id' => '0025', 'conditions' => ['high' => true, 'deck' => true, 'upward' => true], 'rates' => ['high' => '90', 'deck' => '90']])]);
    expect($result['lines'][0])->toMatchArray(['surcharge_rate' => '20', 'unit_price' => '290']);
    expect($result['lines'][0]['note'])->toContain('4.0m未満');
});

test('free accessories ignore site conditions while construction accessories retain their minimum classification', function (): void {
    $catalog = quotationCatalogFixture();
    $generator = array_first(array_filter($catalog, fn (array $item): bool => $item['name'] === '発電機使用100V'));
    $result = calculateQuotationFixture([quotationLineFixture(['catalog_id' => $generator['id'], 'quantity' => '1', 'conditions' => ['high' => true]])]);
    expect($result['construction_subtotal'])->toBe('6000');
    expect($result['lines'][0]['surcharge_rate'])->toBe('0');
});

test('NET is a rounded reference and all thirty lines remain in the breakdown', function (): void {
    $result = calculateQuotationFixture(array_fill(0, 30, quotationLineFixture(['quantity' => '1'])), ['net_enabled' => true, 'net_multiplier' => '0.9']);
    expect($result)->toMatchArray(['total' => '3600', 'net' => '3240', 'display_mode' => 'lump_sum']);
    expect($result['lines'])->toHaveCount(30);
});

test('custom minimum charges never reduce the construction subtotal', function (string $minimum, string $charge, string $total): void {
    $items = [quotationLineFixture(['quantity' => '50'])];

    $result = calculateQuotationFixture($items, ['small_job' => true, 'minimum_charge' => $minimum]);

    expect($result)->toMatchArray(['construction_subtotal' => '6000', 'small_job_charge' => $charge, 'total' => $total, 'warnings' => []]);
})->with([
    'custom minimum above subtotal' => ['12500', '6500', '12500'],
    'minimum equals subtotal' => ['6000', '0', '6000'],
    'minimum below subtotal' => ['5000', '0', '6000'],
    'minimum disabled' => ['0', '0', '6000'],
]);

test('minimum warnings appear only for an unapplied construction shortfall', function (string $quantity, bool $smallJob, array $warnings): void {
    $items = [quotationLineFixture(['quantity' => $quantity])];

    $result = calculateQuotationFixture($items, ['minimum_charge' => '6000', 'small_job' => $smallJob]);

    expect($result['warnings'])->toBe($warnings);
})->with([
    'below minimum without surcharge' => ['49', false, ['施工小計が最低施工費未満です。少量施工加算の適用をご確認ください。']],
    'below minimum with surcharge' => ['49', true, []],
    'exact minimum' => ['50', false, []],
    'above minimum' => ['51', false, []],
]);

test('free-only quotations do not warn about a construction minimum', function (): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '納品', 'free_unit' => '式', 'free_price' => '1', 'quantity' => '1']);

    $result = calculateQuotationFixture([$item]);

    expect($result)->toMatchArray(['construction_subtotal' => '0', 'small_job_charge' => '0', 'warnings' => []]);
});

test('the selected display mode is retained through five lines and six lines force lump sum', function (int $count, string $selected, string $expected): void {
    $items = array_fill(0, $count, quotationLineFixture());

    $result = calculateQuotationFixture($items, ['display_mode' => $selected]);

    expect($result['display_mode'])->toBe($expected);
})->with([
    'five detailed lines' => [5, 'detail', 'detail'],
    'six detailed lines' => [6, 'detail', 'lump_sum'],
    'explicit lump sum with one line' => [1, 'lump_sum', 'lump_sum'],
]);

test('additive and multiplicative adjustments apply before rounding', function (string $adjustment, string $value, string $base, string $price): void {
    $item = quotationLineFixture(['adjustment' => $adjustment, 'adjustment_value' => $value]);

    $result = calculateQuotationFixture([$item]);

    expect($result['lines'][0])->toMatchArray(['base_price' => $base, 'unit_price' => $price]);
})->with([
    'positive adjustment at half-up boundary' => ['add', '25', '145', '150'],
    'discount to zero' => ['add', '-120', '0', '0'],
    'fractional multiplier' => ['multiply', '1.25', '150.00', '150'],
    'zero multiplier' => ['multiply', '0', '0', '0'],
]);

test('negative adjusted prices and missing catalog selections cannot be calculated', function (array $changes, string $message): void {
    $item = quotationLineFixture($changes);

    expect(fn (): array => calculateQuotationFixture([$item]))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'discount below zero' => [['adjustment' => 'add', 'adjustment_value' => '-121'], '調整後の単価は0円以上にしてください。'],
    'unknown product' => [['catalog_id' => 'missing'], '選択された品目が見つかりません。'],
]);

test('calculated lines preserve their public fields and consecutive positions', function (): void {
    $master = quotationLineFixture([
        'quantity' => '2', 'adjustment' => 'add', 'adjustment_value' => '30',
        'conditions' => ['deck' => true], 'rates' => ['deck' => '10'], 'other_rate' => '5',
        'length' => '300', 'processing_mode' => 'manual', 'processing_fee' => '7', 'note' => '現場確認',
    ]);
    $free = quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_specification' => '特注', 'free_unit' => '式', 'free_price' => '12.50', 'quantity' => '0.5']);

    $result = calculateQuotationFixture([$master, $free]);

    expect($result['lines'])->toBe([
        ['position' => 1, 'name' => '差筋アンカー', 'specification' => 'D10（有効長=300L）', 'quantity' => '2', 'unit' => '本', 'base_price' => '150', 'surcharge_rate' => '15', 'processing_fee' => '7', 'needs_processing' => true, 'unit_price' => '177', 'amount' => '354', 'construction' => true, 'note' => 'デッキ貫通 / その他割増5% / 現場確認'],
        ['position' => 2, 'name' => '材料', 'specification' => '特注', 'quantity' => '0.5', 'unit' => '式', 'base_price' => '12.50', 'surcharge_rate' => '0', 'processing_fee' => '0', 'needs_processing' => false, 'unit_price' => '12.50', 'amount' => '6', 'construction' => false, 'note' => ''],
    ]);
    expect(array_diff_key($result, ['lines' => true]))->toBe([
        'rounding_policy' => 'line_half_up_yen',
        'subtotal' => '360', 'construction_subtotal' => '354', 'small_job_charge' => '0',
        'holiday_charge' => '0', 'expenses' => '0', 'total' => '360', 'net' => null,
        'display_mode' => 'detail', 'warnings' => ['施工小計が最低施工費未満です。少量施工加算の適用をご確認ください。'],
    ]);
});

test('cutting can be declined while retaining the requested construction length', function (): void {
    $item = quotationLineFixture(['length' => '300', 'processing_mode' => 'none']);

    $result = calculateQuotationFixture([$item]);

    expect($result['lines'][0])->toMatchArray(['processing_fee' => '0', 'needs_processing' => true, 'unit_price' => '120', 'specification' => 'D10（有効長=300L）']);
});

test('NET rounds a fractional total to the nearest yen with halves rounded up', function (): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '納品', 'free_unit' => '式', 'free_price' => '123', 'quantity' => '1']);

    $result = calculateQuotationFixture([$item], ['net_enabled' => true, 'net_multiplier' => '0.5']);

    expect($result['total'])->toBe('123');
    expect($result['net'])->toBe('62');
});

test('bolts and rebar use the customer-facing chemical-anchor name', function (string $catalogId, string $specification): void {
    $item = quotationLineFixture(['catalog_id' => $catalogId]);

    $result = calculateQuotationFixture([$item]);

    expect($result['lines'][0])->toMatchArray(['name' => 'ケミカルアンカー', 'specification' => $specification]);
})->with([
    'threaded bolt' => ['0003', 'Ｍ10'],
    'rebar' => ['0017', 'Ｄ10'],
]);

test('free entries allow an omitted specification and a null note', function (): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '納品', 'free_unit' => '式', 'free_price' => '0', 'note' => null]);

    $result = calculateQuotationFixture([$item]);

    expect($result['lines'][0])->toMatchArray(['name' => '納品', 'unit' => '式', 'specification' => '', 'note' => '']);
});

test('empty customer notes never add a trailing separator to condition notes', function (?string $note): void {
    $item = quotationLineFixture(['conditions' => ['deck' => true], 'note' => $note]);

    $result = calculateQuotationFixture([$item]);

    expect($result['lines'][0]['note'])->toBe('デッキ貫通');
})->with(['empty' => [''], 'null' => [null]]);

test('native numeric inputs preserve exact unit rates and return rounded yen totals as strings', function (array $changes, array $settings, string $price, string $total): void {
    $item = quotationLineFixture($changes);

    $result = calculateQuotationFixture([$item], $settings);

    expect($result['lines'][0]['unit_price'])->toBe($price);
    expect($result['total'])->toBe($total);
})->with([
    'floating expenses' => [[], ['expenses' => 12.5], '120', '12013'],
    'floating minimum' => [['quantity' => '1'], ['minimum_charge' => 10000.0, 'small_job' => true], '120', '10000'],
    'floating additive adjustment' => [['adjustment' => 'add', 'adjustment_value' => 12.5], [], '130', '13000'],
    'floating multiplier' => [['adjustment' => 'multiply', 'adjustment_value' => 1.25], [], '150', '15000'],
    'floating direct price' => [['adjustment' => 'direct', 'adjustment_value' => 123.45], [], '120', '12000'],
    'floating condition rate' => [['conditions' => ['deck' => true], 'rates' => ['deck' => 12.5]], [], '140', '14000'],
    'floating other rate' => [['other_rate' => 2.5], [], '120', '12000'],
    'floating length' => [['length' => 300.25], [], '130', '13000'],
    'floating manual fee' => [['length' => '300', 'processing_mode' => 'manual', 'processing_fee' => 7.5], [], '127.5', '12750'],
    'floating free price and quantity' => [['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => 12.5, 'quantity' => 2.125], [], '12.5', '27'],
]);

test('extended line amounts use exact multiplication followed by half-up yen rounding', function (string $price, string $quantity, string $amount): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => $price, 'quantity' => $quantity]);

    $result = calculateQuotationFixture([$item]);

    expect($result['lines'][0])->toMatchArray(['unit_price' => $price, 'quantity' => $quantity, 'amount' => $amount]);
    expect($result['total'])->toBe($amount);
})->with([
    'reported fractional total' => ['123.45', '2.5', '309'],
    'just below half yen' => ['499.99', '0.001', '0'],
    'exact half yen' => ['500', '0.001', '1'],
    'just above half yen' => ['500.01', '0.001', '1'],
    'multiply before rounding the unit rate' => ['0.49', '100', '49'],
]);

test('subtotals sum rounded lines instead of rounding their exact combined value', function (string $price, string $total): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => $price, 'quantity' => '1']);

    $result = calculateQuotationFixture([$item, $item]);

    expect($result)->toMatchArray(['subtotal' => $total, 'total' => $total]);
})->with([
    'two amounts below half' => ['0.49', '0'],
    'two exact halves' => ['0.50', '2'],
]);

test('expenses settle to whole yen before being added to the total', function (string $expenses, string $rounded, string $total): void {
    $item = quotationLineFixture(['quantity' => '1']);

    $result = calculateQuotationFixture([$item], ['expenses' => $expenses]);

    expect($result['expenses'])->toBe($rounded);
    expect($result['total'])->toBe($total);
})->with([
    'below half' => ['12.49', '12', '132'],
    'exact half' => ['12.50', '13', '133'],
    'above half' => ['12.51', '13', '133'],
]);

test('the construction minimum uses rounded construction lines and excludes rounded free lines', function (): void {
    $construction = quotationLineFixture(['quantity' => '1.005']);
    $free = quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => '0.5', 'quantity' => '1']);

    $result = calculateQuotationFixture([$construction, $free], ['small_job' => true, 'minimum_charge' => '150']);

    expect($result)->toMatchArray(['construction_subtotal' => '121', 'subtotal' => '122', 'small_job_charge' => '29', 'total' => '151']);
});

test('nonconstruction catalog services remain excluded from the construction minimum', function (): void {
    $item = quotationLineFixture(['catalog_id' => '0901', 'quantity' => '1']);

    $result = calculateQuotationFixture([$item], ['small_job' => true, 'minimum_charge' => '50000']);

    expect($result['lines'][0]['construction'])->toBeFalse();
    expect($result)->toMatchArray(['construction_subtotal' => '0', 'small_job_charge' => '0', 'total' => '25000', 'warnings' => []]);
});

test('NET is calculated from the settled whole-yen total', function (): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => '2.49', 'quantity' => '1']);

    $result = calculateQuotationFixture([$item], ['net_enabled' => true, 'net_multiplier' => '2']);

    expect($result)->toMatchArray(['total' => '2', 'net' => '4']);
});

test('native quantities and NET multipliers are normalized at the calculation boundary', function (): void {
    $item = quotationLineFixture(['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => 123, 'quantity' => 1.5]);

    $result = calculateQuotationFixture([$item], ['net_enabled' => true, 'net_multiplier' => 0.5]);

    expect($result['lines'][0]['quantity'])->toBe('1.5');
    expect($result['net'])->toBe('93');
});
