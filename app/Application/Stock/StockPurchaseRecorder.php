<?php

declare(strict_types=1);

namespace App\Application\Stock;

use App\Domain\Stock\Enums\ScheduleStockSourceType;
use App\Domain\Stock\Enums\StockTransactionType;
use App\Domain\Stock\ValueObjects\StockQuantity;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Models\Stock;
use App\Models\StockPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single write path for term-month purchase quantities. Stores the absolute
 * purchased quantity for a stock and term, mirrors the delta as an immutable
 * ledger transaction, and keeps stocks.current_quantity in sync.
 *
 * Each write owns its transaction. The stock row is locked before the
 * purchase row, matching the lock order used by stock reconciliation.
 */
class StockPurchaseRecorder
{
    /**
     * Set the purchased quantity for a stock in a term. No-op when the value
     * is unchanged.
     *
     * @throws ValidationException
     */
    public function record(int $stockId, StockTerm $term, string $quantity, ?User $actor, ?string $description = null): void
    {
        DB::transaction(function () use ($stockId, $term, $quantity, $actor, $description): void {
            $this->recordWithinTransaction($stockId, $term, $quantity, $actor, $description);
        });
    }

    private function recordWithinTransaction(int $stockId, StockTerm $term, string $quantity, ?User $actor, ?string $description): void
    {
        $stock = Stock::query()->lockForUpdate()->findOrFail($stockId);

        $desiredQuantity = StockQuantity::tryFromDecimal($quantity);

        if (! $desiredQuantity instanceof StockQuantity || $desiredQuantity->isNegative()) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」の仕入数が範囲外です。",
            ]);
        }

        if (! $stock->allows_fractional_quantity && ! $desiredQuantity->isWhole()) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」は整数のみ入力できます。",
            ]);
        }

        $desired = $desiredQuantity->milliUnits();

        $purchase = StockPurchase::query()
            ->where('stock_id', $stock->id)
            ->whereDate('term_starts_on', $term->startsOn()->toDateString())
            ->lockForUpdate()
            ->first();

        $applied = $purchase === null ? 0 : StockQuantity::fromDecimal($purchase->quantity)->milliUnits();
        $delta = $desired - $applied;

        if ($delta === 0) {
            return;
        }

        if (! $stock->is_active && $delta > 0) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」は無効化されているため、仕入数を増やせません。",
            ]);
        }

        $newQuantity = StockQuantity::fromDecimal($stock->current_quantity)->milliUnits() + $delta;

        if ($newQuantity > StockQuantity::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」の在庫数が大きすぎます。",
            ]);
        }

        if ($newQuantity < -StockQuantity::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                'quantity' => "在庫「{$stock->name}」の在庫数が扱える範囲を超えます。",
            ]);
        }

        if ($purchase === null) {
            $purchase = StockPurchase::query()->create([
                'stock_id' => $stock->id,
                'term_starts_on' => $term->startsOn()->toDateString(),
                'quantity' => $desiredQuantity->toDecimalString(),
            ]);
        } else {
            $purchase->update([
                'quantity' => $desiredQuantity->toDecimalString(),
            ]);
        }

        $stock->transactions()->create([
            'transaction_type' => $delta > 0 ? StockTransactionType::StockIn : StockTransactionType::Correction,
            'quantity_delta' => StockQuantity::fromMilliUnits($delta)->toDecimalString(),
            'balance_after' => StockQuantity::fromMilliUnits($newQuantity)->toDecimalString(),
            'source_type' => ScheduleStockSourceType::StockPurchase,
            'source_id' => $purchase->id,
            'stock_name_snapshot' => $stock->name,
            'description' => $description,
            'created_by' => $actor?->id,
        ]);

        $stock->forceFill([
            'current_quantity' => StockQuantity::fromMilliUnits($newQuantity)->toDecimalString(),
        ])->save();
    }
}
