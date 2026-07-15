<?php

declare(strict_types=1);

use App\Services\Stock\ParsedStockMention;
use App\Services\Stock\ScheduleContentStockParser;
use App\Services\Stock\StockCatalogEntry;
use App\Services\Stock\StockNameNormalizer;
use App\StockIdentificationMethod;
use App\StockMentionStatus;

/**
 * @param  array{method?: StockIdentificationMethod, active?: bool, fractional?: bool, stockName?: string}  $options
 */
function stockEntry(int $stockId, string $matchText, array $options = []): StockCatalogEntry
{
    return new StockCatalogEntry(
        stockId: $stockId,
        stockName: $options['stockName'] ?? $matchText,
        normalizedMatchText: new StockNameNormalizer()->normalize($matchText),
        identificationMethod: $options['method'] ?? StockIdentificationMethod::CurrentName,
        isActive: $options['active'] ?? true,
        allowsFractionalQuantity: $options['fractional'] ?? false,
    );
}

/**
 * @return list<StockCatalogEntry>
 */
function defaultCatalog(): array
{
    return [
        stockEntry(15, 'Milk'),
        stockEntry(16, 'Milk Powder'),
        stockEntry(17, 'Milk 2L'),
        stockEntry(42, 'Desk'),
        stockEntry(50, '牛乳'),
        stockEntry(51, '牛乳パック'),
        stockEntry(50, 'ミルク', ['method' => StockIdentificationMethod::Alias, 'stockName' => '牛乳']),
        stockEntry(60, 'ウエス', ['fractional' => true]),
    ];
}

test('recognizes supported quantity separators', function (string $content): void {
    $result = new ScheduleContentStockParser()->parse($content, defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(15)
        ->and($result->mentions[0]->status)->toBe(StockMentionStatus::Recognized)
        ->and($result->mentions[0]->quantity)->toBe('2.000');
})->with([
    'plain space' => ['Milk 2'],
    'colon' => ['Milk: 2'],
    'x' => ['Milk x2'],
    'multiplication sign' => ['Milk ×2'],
    'equals' => ['Milk = 2'],
    'full-width digit and ideographic space' => ['Milk　２'],
    'full-width colon' => ['Milk：2'],
    'trailing period' => ['Milk 2.'],
    'trailing comma' => ['Milk 2,'],
]);

test('recognizes japanese names with and without spacing', function (string $content): void {
    $result = new ScheduleContentStockParser()->parse($content, defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(50)
        ->and($result->mentions[0]->quantity)->toBe('2.000');
})->with([
    'no separator' => ['牛乳2'],
    'space separator' => ['牛乳 2'],
    'full-width colon and digit' => ['牛乳：２'],
]);

test('recognizes aliases and reports the alias identification method', function (): void {
    $result = new ScheduleContentStockParser()->parse('ミルク×3', defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(50)
        ->and($result->mentions[0]->stockNameSnapshot)->toBe('牛乳')
        ->and($result->mentions[0]->identificationMethod)->toBe(StockIdentificationMethod::Alias)
        ->and($result->mentions[0]->quantity)->toBe('3.000');
});

test('does not match inside larger words', function (string $content): void {
    $result = new ScheduleContentStockParser()->parse($content, defaultCatalog());

    expect($result->mentions)->toBe([]);
})->with([
    'suffix' => ['Milkshake 2'],
    'prefix' => ['MyMilk 2'],
]);

test('matches the longest catalog name first', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk Powder 2', defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(16)
        ->and($result->mentions[0]->quantity)->toBe('2.000');
});

test('matches japanese compound names over their prefixes', function (): void {
    $result = new ScheduleContentStockParser()->parse('牛乳パック 2', defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(51);
});

test('handles catalog names that contain digits', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk 2L 3', defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(17)
        ->and($result->mentions[0]->quantity)->toBe('3.000');
});

test('rejects a quantity glued to a following word', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk 2L 3', [stockEntry(15, 'Milk')]);

    expect($result->mentions)->toBe([]);
});

test('aggregates multiple mentions of the same stock', function (): void {
    $result = new ScheduleContentStockParser()->parse(
        'Milk 2 in the morning and Milk 3 in the afternoon.',
        defaultCatalog(),
    );

    expect($result->mentions)->toHaveCount(2)
        ->and($result->aggregateUsageMilliUnits())->toBe([15 => 5000]);
});

test('does not scan for quantities across line breaks', function (): void {
    $result = new ScheduleContentStockParser()->parse("Milk\n2", defaultCatalog());

    expect($result->mentions)->toBe([]);
});

test('does not scan for quantities across sentence terminators', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk. 2', defaultCatalog());

    expect($result->mentions)->toBe([]);
});

test('stops scanning after five separator characters', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk :::::: 2', defaultCatalog());

    expect($result->mentions)->toBe([]);
});

test('ignores unknown words without fuzzy matching', function (): void {
    $result = new ScheduleContentStockParser()->parse('mlik 2 dsek 10', defaultCatalog());

    expect($result->mentions)->toBe([]);
});

test('drops mentions without a quantity token', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk is delivered daily', defaultCatalog());

    expect($result->mentions)->toBe([]);
});

test('flags invalid quantities without aggregating them', function (string $content, ?string $expectedQuantity): void {
    $result = new ScheduleContentStockParser()->parse($content, defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->status)->toBe(StockMentionStatus::InvalidQuantity)
        ->and($result->mentions[0]->quantity)->toBe($expectedQuantity)
        ->and($result->mentions[0]->quantityMilliUnits)->toBeNull()
        ->and($result->aggregateUsageMilliUnits())->toBe([]);
})->with([
    'zero' => ['Milk 0', '0.000'],
    'negative' => ['Milk -2', '-2.000'],
    'fractional when not allowed' => ['Desk 1.5', '1.500'],
    'integer overflow' => ['Milk 12345678901234567890', null],
    'too many decimal places' => ['ウエス 0.0001', null],
]);

test('accepts fractional quantities when the stock allows them', function (): void {
    $result = new ScheduleContentStockParser()->parse('ウエス 1.5', defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->status)->toBe(StockMentionStatus::Recognized)
        ->and($result->mentions[0]->quantity)->toBe('1.500')
        ->and($result->aggregateUsageMilliUnits())->toBe([60 => 1500]);
});

test('marks mentions of inactive stocks and keeps them in the aggregate', function (): void {
    $result = new ScheduleContentStockParser()->parse('旧資材 2', [stockEntry(70, '旧資材', ['active' => false])]);

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->status)->toBe(StockMentionStatus::InactiveStock)
        ->and($result->aggregateUsageMilliUnits())->toBe([70 => 2000]);
});

test('reports code-point offsets into the original content', function (): void {
    $content = '使用 牛乳 2 と Milk 3';
    $result = new ScheduleContentStockParser()->parse($content, defaultCatalog());

    expect($result->mentions)->toHaveCount(2);

    [$first, $second] = $result->mentions;

    expect($first->matchedText)->toBe('牛乳')
        ->and($first->startOffset)->toBe(3)
        ->and($first->endOffset)->toBe(5)
        ->and($first->quantityStartOffset)->toBe(6)
        ->and($first->quantityEndOffset)->toBe(7)
        ->and($second->matchedText)->toBe('Milk')
        ->and($second->startOffset)->toBe(10)
        ->and($second->endOffset)->toBe(14)
        ->and($second->quantityStartOffset)->toBe(15)
        ->and($second->quantityEndOffset)->toBe(16);
});

test('maps offsets through full-width characters back to the original text', function (): void {
    $result = new ScheduleContentStockParser()->parse('ＭＩＬＫ ２', defaultCatalog());

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(15)
        ->and($result->mentions[0]->matchedText)->toBe('ＭＩＬＫ')
        ->and($result->mentions[0]->startOffset)->toBe(0)
        ->and($result->mentions[0]->endOffset)->toBe(4)
        ->and($result->mentions[0]->quantity)->toBe('2.000');
});

test('composes halfwidth katakana with voiced sound marks', function (string $content): void {
    $result = new ScheduleContentStockParser()->parse($content, [stockEntry(70, 'ガス')]);

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->stockId)->toBe(70)
        ->and($result->mentions[0]->matchedText)->toBe(mb_substr($content, 0, 3))
        ->and($result->mentions[0]->startOffset)->toBe(0)
        ->and($result->mentions[0]->endOffset)->toBe(3)
        ->and($result->mentions[0]->quantity)->toBe('2.000');
})->with([
    'halfwidth katakana' => ['ｶﾞｽ 2'],
    'combining voiced mark' => ["カ\u{3099}ス 2"],
]);

test('maps the end offset through a composed pair at the end of a match', function (): void {
    $result = new ScheduleContentStockParser()->parse('ﾚﾝｶﾞ 3', [stockEntry(71, 'レンガ')]);

    expect($result->mentions)->toHaveCount(1)
        ->and($result->mentions[0]->matchedText)->toBe('ﾚﾝｶﾞ')
        ->and($result->mentions[0]->startOffset)->toBe(0)
        ->and($result->mentions[0]->endOffset)->toBe(4)
        ->and($result->mentions[0]->quantityStartOffset)->toBe(5)
        ->and($result->mentions[0]->quantityEndOffset)->toBe(6)
        ->and($result->mentions[0]->quantity)->toBe('3.000');
});

test('recognizes mentions on both sides of mixed content', function (): void {
    $content = "朝の配達:\n牛乳 2\nDesk 10";
    $result = new ScheduleContentStockParser()->parse($content, defaultCatalog());

    expect($result->aggregateUsageMilliUnits())->toBe([50 => 2000, 42 => 10000]);
});

test('converts between decimal strings and milli-units', function (): void {
    expect(ScheduleContentStockParser::milliUnitsToDecimalString(2000))->toBe('2.000')
        ->and(ScheduleContentStockParser::milliUnitsToDecimalString(-7500))->toBe('-7.500')
        ->and(ScheduleContentStockParser::milliUnitsToDecimalString(0))->toBe('0.000')
        ->and(ScheduleContentStockParser::milliUnitsToDecimalString(1))->toBe('0.001')
        ->and(ScheduleContentStockParser::decimalStringToMilliUnits('2.000'))->toBe(2000)
        ->and(ScheduleContentStockParser::decimalStringToMilliUnits('-7.500'))->toBe(-7500)
        ->and(ScheduleContentStockParser::decimalStringToMilliUnits('18'))->toBe(18000)
        ->and(ScheduleContentStockParser::decimalStringToMilliUnits('0.001'))->toBe(1);
});

test('returns no mentions for empty content or an empty catalog', function (): void {
    $parser = new ScheduleContentStockParser;

    expect($parser->parse('', defaultCatalog())->mentions)->toBe([])
        ->and($parser->parse('   ', defaultCatalog())->mentions)->toBe([])
        ->and($parser->parse('Milk 2', [])->mentions)->toBe([]);
});

test('mentions expose every field needed for persistence', function (): void {
    $result = new ScheduleContentStockParser()->parse('Milk 2', defaultCatalog());

    expect($result->mentions[0])->toBeInstanceOf(ParsedStockMention::class)
        ->and($result->mentions[0]->stockNameSnapshot)->toBe('Milk')
        ->and($result->mentions[0]->identificationMethod)->toBe(StockIdentificationMethod::CurrentName);
});
