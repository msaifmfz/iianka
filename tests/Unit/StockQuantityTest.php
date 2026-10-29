<?php

declare(strict_types=1);

use App\Domain\Stock\ValueObjects\StockQuantity;

mutates(StockQuantity::class);

test('it creates canonical quantities from decimal strings', function (string $input, int $milliUnits, string $canonical): void {
    $quantity = StockQuantity::fromDecimal($input);

    expect($quantity->milliUnits())->toBe($milliUnits)
        ->and($quantity->toDecimalString())->toBe($canonical)
        ->and((string) $quantity)->toBe($canonical);
})->with([
    'whole number' => ['18', 18_000, '18.000'],
    'fractional quantity' => ['1.25', 1_250, '1.250'],
    'smallest precision' => ['0.001', 1, '0.001'],
    'smallest negative quantity' => ['-0.001', -1, '-0.001'],
    'zero' => ['0', 0, '0.000'],
    'negative zero' => ['-0.000', 0, '0.000'],
    'negative ledger delta' => ['-7.5', -7_500, '-7.500'],
    'maximum quantity' => ['999999999.999', StockQuantity::MAX_MILLI_UNITS, '999999999.999'],
    'minimum quantity' => ['-999999999.999', -999_999_999_999, '-999999999.999'],
    'leading zeroes' => ['0002.010', 2_010, '2.010'],
    'leading zeroes beyond the integer digit limit' => ['00000000000002.010', 2_010, '2.010'],
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

test('quantity signs distinguish zero from the smallest positive and negative values', function (
    string $decimal,
    bool $positive,
    bool $negative,
    bool $zero,
): void {
    $quantity = StockQuantity::fromDecimal($decimal);

    expect($quantity->isPositive())->toBe($positive);
    expect($quantity->isNegative())->toBe($negative);
    expect($quantity->isZero())->toBe($zero);
})->with([
    'smallest negative' => ['-0.001', false, true, false],
    'zero' => ['0', false, false, true],
    'negative zero' => ['-0.000', false, false, true],
    'smallest positive' => ['0.001', true, false, false],
]);

test('milli unit boundaries preserve their exact decimal values', function (int $milliUnits, string $decimal): void {
    $quantity = StockQuantity::fromMilliUnits($milliUnits);

    expect($quantity->milliUnits())->toBe($milliUnits);
    expect($quantity->toDecimalString())->toBe($decimal);
})->with([
    'minimum' => [-999_999_999_999, '-999999999.999'],
    'maximum' => [999_999_999_999, '999999999.999'],
]);

test('milli unit values outside either persisted boundary are rejected', function (int $milliUnits): void {
    expect(fn (): StockQuantity => StockQuantity::fromMilliUnits($milliUnits))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'below minimum' => [-1_000_000_000_000],
    'above maximum' => [1_000_000_000_000],
]);

test('subtracting one milli unit cannot underflow the persisted quantity range', function (): void {
    $minimum = StockQuantity::fromMilliUnits(-999_999_999_999);

    expect(fn (): StockQuantity => $minimum->minus(StockQuantity::fromMilliUnits(1)))
        ->toThrow(InvalidArgumentException::class);
});
