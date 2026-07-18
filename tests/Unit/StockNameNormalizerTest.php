<?php

declare(strict_types=1);

use App\Domain\Stock\Parsing\StockNameNormalizer;

test('normalizes stock names deterministically', function (string $input, string $expected): void {
    expect(new StockNameNormalizer()->normalize($input))->toBe($expected);
})->with([
    'lowercases ascii' => ['MILK', 'milk'],
    'folds full-width letters' => ['ＭＩＬＫ', 'milk'],
    'folds full-width digits' => ['Ｍｉｌｋ ２Ｌ', 'milk 2l'],
    'collapses consecutive spaces' => ['milk    powder', 'milk powder'],
    'collapses tabs and newlines' => ["milk\t\npowder", 'milk powder'],
    'trims leading and trailing whitespace' => ['  milk  ', 'milk'],
    'normalizes non-breaking spaces' => ["milk\u{00A0}powder", 'milk powder'],
    'normalizes ideographic spaces' => ['牛乳　パック', '牛乳 パック'],
    'keeps japanese text without romanization' => ['ミルク', 'ミルク'],
    'folds half-width katakana' => ['ﾐﾙｸ', 'ミルク'],
    'expands nfkc compatibility characters' => ['㍑', 'リットル'],
    'keeps meaningful punctuation' => ['milk-2l', 'milk-2l'],
]);
