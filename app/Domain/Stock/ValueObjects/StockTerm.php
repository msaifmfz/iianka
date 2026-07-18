<?php

declare(strict_types=1);

namespace App\Domain\Stock\ValueObjects;

use App\Services\BusinessDate;
use Illuminate\Support\Carbon;

/**
 * Immutable term month for stock accounting. A term runs from the 21st of a
 * month to the 20th of the following month, matching the attendance billing
 * period convention. The start date is always the 21st.
 */
final readonly class StockTerm
{
    private function __construct(
        private Carbon $startsOn,
    ) {}

    /**
     * Build the term for a month given as a date string (e.g. "2026-06-01").
     * Only the year and month are significant; the term starts on that
     * month's 21st.
     */
    public static function fromMonth(string $month): self
    {
        return new self(Carbon::parse($month)->startOfMonth()->day(21)->startOfDay());
    }

    /**
     * Build the term containing the given date: days 1-20 belong to the term
     * that started on the previous month's 21st.
     */
    public static function containing(Carbon $date): self
    {
        $month = $date->copy()->startOfDay()->startOfMonth();

        if ($date->day <= 20) {
            $month = $month->subMonthNoOverflow();
        }

        return new self($month->day(21));
    }

    public static function current(): self
    {
        return self::containing(BusinessDate::today());
    }

    public function startsOn(): Carbon
    {
        return $this->startsOn->copy();
    }

    /**
     * Last day of the term (the 20th of the following month).
     */
    public function endsOn(): Carbon
    {
        return $this->startsOn->copy()->addMonthNoOverflow()->day(20);
    }

    /**
     * First day after the term (the next term's 21st), for half-open ranges.
     */
    public function endExclusive(): Carbon
    {
        return $this->startsOn->copy()->addMonthNoOverflow()->day(21);
    }

    /**
     * The three usage buckets of the term as half-open date ranges:
     * 21st to end of month, 1st to 10th, and 11th to 20th.
     *
     * @return list<array{starts_on: Carbon, end_exclusive: Carbon, label: string}>
     */
    public function buckets(): array
    {
        $firstOfNextMonth = $this->startsOn->copy()->addMonthNoOverflow()->startOfMonth();

        return [
            [
                'starts_on' => $this->startsOn(),
                'end_exclusive' => $firstOfNextMonth->copy(),
                'label' => '21日〜月末',
            ],
            [
                'starts_on' => $firstOfNextMonth->copy(),
                'end_exclusive' => $firstOfNextMonth->copy()->addDays(10),
                'label' => '1日〜10日',
            ],
            [
                'starts_on' => $firstOfNextMonth->copy()->addDays(10),
                'end_exclusive' => $this->endExclusive(),
                'label' => '11日〜20日',
            ],
        ];
    }

    public function next(): self
    {
        return new self($this->startsOn->copy()->addMonthNoOverflow());
    }

    public function previous(): self
    {
        return new self($this->startsOn->copy()->subMonthNoOverflow());
    }

    /**
     * e.g. "2026年6月度"
     */
    public function label(): string
    {
        return sprintf('%d年%d月度', $this->startsOn->year, $this->startsOn->month);
    }

    /**
     * e.g. "6/21〜7/20"
     */
    public function rangeLabel(): string
    {
        $endsOn = $this->endsOn();

        return sprintf('%d/%d〜%d/%d', $this->startsOn->month, $this->startsOn->day, $endsOn->month, $endsOn->day);
    }

    /**
     * Month query parameter value (first of the term's start month).
     */
    public function monthParam(): string
    {
        return $this->startsOn->copy()->startOfMonth()->toDateString();
    }
}
