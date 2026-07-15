<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\ScheduleStockBalance;
use App\Models\Stock;
use App\Models\StockPurchase;
use App\Models\StockTransaction;
use App\ScheduleStockSourceType;
use App\Services\BusinessDate;
use App\StockTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the term-month stock report: per stock, the carry-over from the
 * previous term, the purchased quantity, the usage split into the three
 * ten-day buckets, and the resulting total.
 *
 * Usage is attributed to the schedule's business date
 * (construction_schedules.scheduled_on), so this is a planned view: a term
 * total may differ from stocks.current_quantity, which already reflects
 * schedules dated beyond the visible terms. Only construction schedules
 * produce usage today; manual ledger corrections are folded into a separate
 * adjustments component so the carry-over identity
 * total(term) = carry_over(next term) always holds.
 *
 * All aggregation happens in integer milli-units (SUM(ROUND(x * 1000))) to
 * stay exact on drivers that store decimals as floats (SQLite).
 */
class StockTermReportService
{
    /**
     * Report data for the given term and the following term.
     *
     * @return list<array{
     *     label: string,
     *     range_label: string,
     *     term_starts_on: string,
     *     month_param: string,
     *     buckets: list<array{label: string, starts_on: string, ends_on: string}>,
     *     rows: list<array{
     *         stock_id: int,
     *         name: string,
     *         sku: string|null,
     *         is_active: bool,
     *         allows_fractional_quantity: bool,
     *         carry_over: string,
     *         purchased: string,
     *         used: list<string>,
     *         used_total: string,
     *         adjustments: string,
     *         total: string,
     *     }>,
     * }>
     */
    public function build(StockTerm $firstTerm): array
    {
        $secondTerm = $firstTerm->next();

        $stocks = Stock::query()->orderByDesc('is_active')->orderBy('name')->get();
        $purchases = $this->purchaseSums($firstTerm, $secondTerm);
        $usage = $this->usageSums($firstTerm, $secondTerm);
        $adjustments = $this->adjustmentSums($firstTerm, $secondTerm);

        $firstRows = [];
        $secondRows = [];

        foreach ($stocks as $stock) {
            $purchase = $purchases[$stock->id] ?? ['before' => 0, 'first' => 0, 'second' => 0];
            $adjustment = $adjustments[$stock->id] ?? ['before' => 0, 'first' => 0, 'second' => 0];
            $used = $usage[$stock->id] ?? ['before' => 0, 'first' => [0, 0, 0], 'second' => [0, 0, 0]];

            $firstCarryOver = $purchase['before'] + $adjustment['before'] - $used['before'];
            $firstTotal = $firstCarryOver + $purchase['first'] + $adjustment['first'] - array_sum($used['first']);
            $secondTotal = $firstTotal + $purchase['second'] + $adjustment['second'] - array_sum($used['second']);

            if (! $stock->is_active && $this->allZero([
                $firstCarryOver,
                $purchase['first'], $adjustment['first'], ...$used['first'],
                $purchase['second'], $adjustment['second'], ...$used['second'],
            ])) {
                continue;
            }

            $firstRows[] = $this->row($stock, $firstCarryOver, $purchase['first'], $used['first'], $adjustment['first'], $firstTotal);
            $secondRows[] = $this->row($stock, $firstTotal, $purchase['second'], $used['second'], $adjustment['second'], $secondTotal);
        }

        return [
            $this->termPayload($firstTerm, $firstRows),
            $this->termPayload($secondTerm, $secondRows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     label: string,
     *     range_label: string,
     *     term_starts_on: string,
     *     month_param: string,
     *     buckets: list<array{label: string, starts_on: string, ends_on: string}>,
     *     rows: list<array<string, mixed>>,
     * }
     */
    private function termPayload(StockTerm $term, array $rows): array
    {
        return [
            'label' => $term->label(),
            'range_label' => $term->rangeLabel(),
            'term_starts_on' => $term->startsOn()->toDateString(),
            'month_param' => $term->monthParam(),
            'buckets' => array_map(fn (array $bucket): array => [
                'label' => $bucket['label'],
                'starts_on' => $bucket['starts_on']->toDateString(),
                'ends_on' => $bucket['end_exclusive']->copy()->subDay()->toDateString(),
            ], $term->buckets()),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<int>  $usedMilliUnits
     * @return array{
     *     stock_id: int,
     *     name: string,
     *     sku: string|null,
     *     is_active: bool,
     *     allows_fractional_quantity: bool,
     *     carry_over: string,
     *     purchased: string,
     *     used: list<string>,
     *     used_total: string,
     *     adjustments: string,
     *     total: string,
     * }
     */
    private function row(Stock $stock, int $carryOver, int $purchased, array $usedMilliUnits, int $adjustments, int $total): array
    {
        return [
            'stock_id' => $stock->id,
            'name' => $stock->name,
            'sku' => $stock->sku,
            'is_active' => $stock->is_active,
            'allows_fractional_quantity' => $stock->allows_fractional_quantity,
            'carry_over' => ScheduleContentStockParser::milliUnitsToDecimalString($carryOver),
            'purchased' => ScheduleContentStockParser::milliUnitsToDecimalString($purchased),
            'used' => array_map(
                ScheduleContentStockParser::milliUnitsToDecimalString(...),
                $usedMilliUnits,
            ),
            'used_total' => ScheduleContentStockParser::milliUnitsToDecimalString(array_sum($usedMilliUnits)),
            'adjustments' => ScheduleContentStockParser::milliUnitsToDecimalString($adjustments),
            'total' => ScheduleContentStockParser::milliUnitsToDecimalString($total),
        ];
    }

    /**
     * @param  list<int>  $values
     */
    private function allZero(array $values): bool
    {
        return array_all($values, fn (int $value): bool => $value === 0);
    }

    /**
     * Purchased milli-units per stock: before the first term, in the first
     * term, and in the second term.
     *
     * @return array<int, array{before: int, first: int, second: int}>
     */
    private function purchaseSums(StockTerm $firstTerm, StockTerm $secondTerm): array
    {
        $firstStart = $firstTerm->startsOn()->toDateString();
        $secondStart = $secondTerm->startsOn()->toDateString();

        $sums = [];

        $rows = StockPurchase::query()
            ->whereDate('term_starts_on', '<=', $secondStart)
            ->groupBy('stock_id')
            ->selectRaw(
                'stock_id,'
                .' SUM(CASE WHEN date(term_starts_on) < ? THEN ROUND(quantity * 1000) ELSE 0 END) AS before_first,'
                .' SUM(CASE WHEN date(term_starts_on) = ? THEN ROUND(quantity * 1000) ELSE 0 END) AS in_first,'
                .' SUM(CASE WHEN date(term_starts_on) = ? THEN ROUND(quantity * 1000) ELSE 0 END) AS in_second',
                [$firstStart, $firstStart, $secondStart],
            )
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $sums[(int) $row->stock_id] = [
                'before' => (int) $row->before_first,
                'first' => (int) $row->in_first,
                'second' => (int) $row->in_second,
            ];
        }

        return $sums;
    }

    /**
     * Applied schedule usage milli-units per stock, attributed by the
     * schedule's business date: everything before the first term, plus the
     * three buckets of each visible term.
     *
     * @return array<int, array{before: int, first: list<int>, second: list<int>}>
     */
    private function usageSums(StockTerm $firstTerm, StockTerm $secondTerm): array
    {
        $bucketCases = [];
        $bindings = [$firstTerm->startsOn()->toDateString()];

        foreach ([...$firstTerm->buckets(), ...$secondTerm->buckets()] as $index => $bucket) {
            $bucketCases[] = "SUM(CASE WHEN date(cs.scheduled_on) >= ? AND date(cs.scheduled_on) < ? THEN ROUND(applied_quantity * 1000) ELSE 0 END) AS bucket_{$index}";
            $bindings[] = $bucket['starts_on']->toDateString();
            $bindings[] = $bucket['end_exclusive']->toDateString();
        }

        $sums = [];

        $rows = ScheduleStockBalance::query()
            ->join('construction_schedules as cs', 'cs.id', '=', 'schedule_stock_balances.schedule_id')
            ->where('schedule_stock_balances.schedule_type', ScheduleStockSourceType::ConstructionSchedule)
            ->whereDate('cs.scheduled_on', '<', $secondTerm->endExclusive()->toDateString())
            ->groupBy('schedule_stock_balances.stock_id')
            ->selectRaw(
                'schedule_stock_balances.stock_id,'
                .' SUM(CASE WHEN date(cs.scheduled_on) < ? THEN ROUND(applied_quantity * 1000) ELSE 0 END) AS before_first,'
                .' '.implode(', ', $bucketCases),
                $bindings,
            )
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $sums[(int) $row->stock_id] = [
                'before' => (int) $row->before_first,
                'first' => [(int) $row->bucket_0, (int) $row->bucket_1, (int) $row->bucket_2],
                'second' => [(int) $row->bucket_3, (int) $row->bucket_4, (int) $row->bucket_5],
            ];
        }

        return $sums;
    }

    /**
     * Manual ledger adjustments (not purchases, not schedule usage) in
     * milli-units per stock, attributed by transaction time converted to the
     * business timezone.
     *
     * @return array<int, array{before: int, first: int, second: int}>
     */
    private function adjustmentSums(StockTerm $firstTerm, StockTerm $secondTerm): array
    {
        $firstStart = $this->appTimezoneBoundary($firstTerm->startsOn());
        $secondStart = $this->appTimezoneBoundary($secondTerm->startsOn());
        $secondEnd = $this->appTimezoneBoundary($secondTerm->endExclusive());

        $sums = [];

        $rows = StockTransaction::query()
            ->whereIn('transaction_type', [
                StockTransactionType::StockIn,
                StockTransactionType::ManualIncrease,
                StockTransactionType::ManualDecrease,
                StockTransactionType::Correction,
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('source_type')
                    ->orWhere('source_type', '!=', ScheduleStockSourceType::StockPurchase);
            })
            ->where('created_at', '<', $secondEnd)
            ->groupBy('stock_id')
            ->selectRaw(
                'stock_id,'
                .' SUM(CASE WHEN created_at < ? THEN ROUND(quantity_delta * 1000) ELSE 0 END) AS before_first,'
                .' SUM(CASE WHEN created_at >= ? AND created_at < ? THEN ROUND(quantity_delta * 1000) ELSE 0 END) AS in_first,'
                .' SUM(CASE WHEN created_at >= ? AND created_at < ? THEN ROUND(quantity_delta * 1000) ELSE 0 END) AS in_second',
                [$firstStart, $firstStart, $secondStart, $secondStart, $secondEnd],
            )
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $sums[(int) $row->stock_id] = [
                'before' => (int) $row->before_first,
                'first' => (int) $row->in_first,
                'second' => (int) $row->in_second,
            ];
        }

        return $sums;
    }

    /**
     * A term boundary (Tokyo midnight) as an app-timezone datetime string for
     * comparing against created_at.
     */
    private function appTimezoneBoundary(Carbon $date): string
    {
        return Carbon::parse($date->toDateString(), BusinessDate::TIMEZONE)
            ->setTimezone(config()->string('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
