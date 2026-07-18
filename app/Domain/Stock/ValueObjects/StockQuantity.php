<?php

declare(strict_types=1);

namespace App\Domain\Stock\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Exact inventory quantity stored at three decimal places.
 *
 * The value object is deliberately persistence-agnostic: it owns the
 * decimal(12,3) range, canonical string representation, and arithmetic used
 * by parsing, reconciliation, purchases, and reporting.
 */
final readonly class StockQuantity implements Stringable
{
    public const int SCALE = 3;

    public const int MAX_MILLI_UNITS = 999_999_999_999;

    private function __construct(private int $milliUnits) {}

    public static function fromDecimal(string $decimal): self
    {
        if (preg_match('/^-?\d+(?:\.\d{1,3})?$/D', $decimal) !== 1) {
            throw new InvalidArgumentException('Stock quantity must be a decimal value with at most three fractional digits.');
        }

        $isNegative = str_starts_with($decimal, '-');
        $unsigned = $isNegative ? substr($decimal, 1) : $decimal;
        [$integerPart, $fractionPart] = array_pad(explode('.', $unsigned, 2), 2, '');

        if (strlen(ltrim($integerPart, '0')) > 9) {
            throw new InvalidArgumentException('Stock quantity exceeds the decimal(12,3) range.');
        }

        $milliUnits = ((int) $integerPart) * 1000
            + (int) str_pad($fractionPart, self::SCALE, '0');

        return self::fromMilliUnits($isNegative ? -$milliUnits : $milliUnits);
    }

    public static function tryFromDecimal(string $decimal): ?self
    {
        try {
            return self::fromDecimal($decimal);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function fromMilliUnits(int $milliUnits): self
    {
        if ($milliUnits < -self::MAX_MILLI_UNITS || $milliUnits > self::MAX_MILLI_UNITS) {
            throw new InvalidArgumentException('Stock quantity exceeds the decimal(12,3) range.');
        }

        return new self($milliUnits);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function milliUnits(): int
    {
        return $this->milliUnits;
    }

    public function plus(self $quantity): self
    {
        return self::fromMilliUnits($this->milliUnits + $quantity->milliUnits);
    }

    public function minus(self $quantity): self
    {
        return self::fromMilliUnits($this->milliUnits - $quantity->milliUnits);
    }

    public function negated(): self
    {
        return self::fromMilliUnits(-$this->milliUnits);
    }

    public function isPositive(): bool
    {
        return $this->milliUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->milliUnits < 0;
    }

    public function isZero(): bool
    {
        return $this->milliUnits === 0;
    }

    public function isWhole(): bool
    {
        return $this->milliUnits % 1000 === 0;
    }

    public function equals(self $quantity): bool
    {
        return $this->milliUnits === $quantity->milliUnits;
    }

    public function toDecimalString(): string
    {
        $absolute = abs($this->milliUnits);

        return ($this->milliUnits < 0 ? '-' : '')
            .intdiv($absolute, 1000)
            .'.'
            .str_pad((string) ($absolute % 1000), self::SCALE, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }
}
