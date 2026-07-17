<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Stock;
use App\Models\StockPurchase;
use App\Models\User;
use App\ScheduleStockSourceType;
use App\StockTransactionType;
use Illuminate\Validation\ValidationException;

/**
 * Single write path for term-month purchase quantities. Stores the absolute
 * purchased quantity for a stock and term, mirrors the delta as an immutable
 * ledger transaction, and keeps stocks.current_quantity in sync.
 *
 * MUST be called inside a database transaction. The stock row is locked
 * before the purchase row, matching the lock order used by
 * ScheduleStockReconciliationService.
 */
class StockPurchaseRecorder
{
    /**
     * decimal(12,3) upper bound, in milli-units.
     */
    private const int MAX_MILLI_UNITS = 999_999_999_999;

    /**
     * Set the purchased quantity for a stock in a term. No-op when the value
     * is unchanged.
     *
     * @throws ValidationException
     */
    public function record(int $stockId, StockTerm $term, string $quantity, ?User $actor, ?string $description = null): void
    {
        $stock = Stock::query()->lockForUpdate()->findOrFail($stockId);

        $desired = ScheduleContentStockParser::decimalStringToMilliUnits($quantity);

        if ($desired < 0 || $desired > self::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」の仕入数が範囲外です。",
            ]);
        }

        $purchase = StockPurchase::query()
            ->where('stock_id', $stock->id)
            ->whereDate('term_starts_on', $term->startsOn()->toDateString())
            ->lockForUpdate()
            ->first();

        $applied = $purchase === null ? 0 : ScheduleContentStockParser::decimalStringToMilliUnits($purchase->quantity);
        $delta = $desired - $applied;

        if ($delta === 0) {
            return;
        }

        if (! $stock->is_active && $delta > 0) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」は無効化されているため、仕入数を増やせません。",
            ]);
        }

        $newQuantity = ScheduleContentStockParser::decimalStringToMilliUnits($stock->current_quantity) + $delta;

        if ($newQuantity > self::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」の在庫数が大きすぎます。",
            ]);
        }

        if ($newQuantity < -self::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」の在庫数が扱える範囲を超えます。",
            ]);
        }

        if ($purchase === null) {
            $purchase = StockPurchase::query()->create([
                'stock_id' => $stock->id,
                'term_starts_on' => $term->startsOn()->toDateString(),
                'quantity' => ScheduleContentStockParser::milliUnitsToDecimalString($desired),
            ]);
        } else {
            $purchase->update([
                'quantity' => ScheduleContentStockParser::milliUnitsToDecimalString($desired),
            ]);
        }

        $stock->transactions()->create([
            'transaction_type' => $delta > 0 ? StockTransactionType::StockIn : StockTransactionType::Correction,
            'quantity_delta' => ScheduleContentStockParser::milliUnitsToDecimalString($delta),
            'balance_after' => ScheduleContentStockParser::milliUnitsToDecimalString($newQuantity),
            'source_type' => ScheduleStockSourceType::StockPurchase,
            'source_id' => $purchase->id,
            'stock_name_snapshot' => $stock->name,
            'description' => $description,
            'created_by' => $actor?->id,
        ]);

        $stock->forceFill([
            'current_quantity' => ScheduleContentStockParser::milliUnitsToDecimalString($newQuantity),
        ])->save();
    }
}
