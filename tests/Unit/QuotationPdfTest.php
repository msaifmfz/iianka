<?php

declare(strict_types=1);

use App\Application\Quotation\QuotationPdf;

mutates(QuotationPdf::class);

test('money formatting preserves significant decimals and groups integer digits', function (string|int $value, string $expected): void {
    expect(QuotationPdf::money($value))->toBe($expected);
})->with([
    'zero' => [0, '0'],
    'integer' => [1234567, '1,234,567'],
    'fractional yen' => ['1234567.012500', '1,234,567.0125'],
    'whole decimal' => ['1000.0000', '1,000'],
    'negative amount' => ['-1234.50', '-1,234.5'],
]);

test('PDF text adds wrapping opportunities only beyond the word boundary', function (string $text, int $limit, string $expected): void {
    expect(QuotationPdf::text($text, $limit)->toHtml())->toBe($expected);
})->with([
    'at boundary' => ['ABCD', 4, 'ABCD'],
    'over boundary' => ['ABCDE', 4, "A\u{200B}B\u{200B}C\u{200B}D\u{200B}E"],
    'separate short words' => ['AB CD', 2, 'AB CD'],
    'graphemes remain intact' => ["a\u{0301}🙂", 1, "a\u{0301}\u{200B}🙂"],
    'adjacent emoji split independently' => ['😀😀', 1, "😀\u{200B}😀"],
    'joined emoji remain intact' => ['👷🏽‍♀️🙂', 1, "👷🏽‍♀️\u{200B}🙂"],
    'regional indicators retain their flag pairs' => ['🇯🇵🇲🇾', 1, "🇯🇵\u{200B}🇲🇾"],
    'HTML escaped' => ['<b>&', 20, '&lt;b&gt;&amp;'],
]);

test('null PDF text is empty and the default wrapping boundary is sixteen graphemes', function (): void {
    expect(QuotationPdf::text(null)->toHtml())->toBe('');
    expect(QuotationPdf::text('ABCDEFGHIJKLMNOP')->toHtml())->toBe('ABCDEFGHIJKLMNOP');
    expect(QuotationPdf::text('ABCDEFGHIJKLMNOPQ')->toHtml())->toContain("P\u{200B}Q");
});

test('a thirteenth note line starts a new PDF table row', function (): void {
    $first = "1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12";

    expect(QuotationPdf::noteParts($first))->toBe([$first]);
    expect(QuotationPdf::noteParts($first."\n13"))->toBe([$first, '13']);
    expect(QuotationPdf::noteParts("first\r\nsecond\rthird"))->toBe(["first\nsecond\nthird"]);
});
