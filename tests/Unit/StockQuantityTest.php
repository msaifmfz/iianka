<?php

declare(strict_types=1);

use App\Domain\Stock\ValueObjects\StockQuantity;

test('it creates canonical quantities from decimal strings', function (string $input, int $milliUnits, string $canonical): void {
    $quantity = StockQuantity::fromDecimal($input);

    expect($quantity->milliUnits())->toBe($milliUnits)
        ->and($quantity->toDecimalString())->toBe($canonical)
        ->and((string) $quantity)->toBe($canonical);
})->with([
    'whole number' => ['18', 18_000, '18.000'],
    'fractional quantity' => ['1.25', 1_250, '1.250'],
    'smallest precision' => ['0.001', 1, '0.001'],
    'negative ledger delta' => ['-7.5', -7_500, '-7.500'],
    'maximum quantity' => ['999999999.999', StockQuantity::MAX_MILLI_UNITS, '999999999.999'],
    'leading zeroes' => ['0002.010', 2_010, '2.010'],
]);

test('it rejects malformed or out of range decimal strings', function (string $input): void {
    expect(fn (): StockQuantity => StockQuantity::fromDecimal($input))
        ->toThrow(InvalidArgumentException::class)
        ->and(StockQuantity::tryFromDecimal($input))->toBeNull();
})->with([
    'empty' => [''],
    'missing integer' => ['.5'],
    'too precise' => ['1.0001'],
    'non numeric' => ['one'],
    'positive out of range' => ['1000000000'],
    'negative out of range' => ['-1000000000'],
]);

test('it performs exact immutable arithmetic', function (): void {
    $starting = StockQuantity::fromDecimal('10.250');
    $delta = StockQuantity::fromDecimal('1.125');

    expect($starting->plus($delta)->toDecimalString())->toBe('11.375')
        ->and($starting->minus($delta)->toDecimalString())->toBe('9.125')
        ->and($delta->negated()->toDecimalString())->toBe('-1.125')
        ->and($starting->toDecimalString())->toBe('10.250');
});

test('it exposes quantity semantics', function (): void {
    expect(StockQuantity::fromDecimal('2')->isWhole())->toBeTrue()
        ->and(StockQuantity::fromDecimal('2.001')->isWhole())->toBeFalse()
        ->and(StockQuantity::fromDecimal('0')->isZero())->toBeTrue()
        ->and(StockQuantity::fromDecimal('1')->isPositive())->toBeTrue()
        ->and(StockQuantity::fromDecimal('-1')->isNegative())->toBeTrue()
        ->and(StockQuantity::fromDecimal('1')->equals(StockQuantity::fromMilliUnits(1000)))->toBeTrue();
});

test('arithmetic cannot exceed the persisted quantity range', function (): void {
    $maximum = StockQuantity::fromMilliUnits(StockQuantity::MAX_MILLI_UNITS);

    expect(fn (): StockQuantity => $maximum->plus(StockQuantity::fromMilliUnits(1)))
        ->toThrow(InvalidArgumentException::class);
});
