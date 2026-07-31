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
 * Adds and corrects term purchase quantities while keeping the immutable
 * stock ledger and current stock balance in sync.
 *
 * Each write locks the stock row before the term aggregate row, matching the
 * lock order used by stock reconciliation.
 */
class StockPurchaseRecorder
{
    /**
     * @throws ValidationException
     */
    public function add(
        int $stockId,
        StockTerm $term,
        string $quantity,
        ?User $actor,
        ?string $description = null,
    ): void {
        DB::transaction(function () use ($stockId, $term, $quantity, $actor, $description): void {
            $stock = Stock::query()->lockForUpdate()->findOrFail($stockId);

            $quantityToAdd = $this->positiveQuantity($stock, $quantity, 'quantity_to_add');

            if (! $stock->is_active) {
                throw ValidationException::withMessages([
                    'quantity_to_add' => "在庫「{$stock->name}」は無効化されているため、仕入を追加できません。",
                ]);
            }

            $purchase = $this->lockedPurchase($stock, $term);
            $purchasedMilliUnits = $purchase instanceof StockPurchase
                ? StockQuantity::fromDecimal($purchase->quantity)->milliUnits()
                : 0;
            $newPurchasedMilliUnits = $purchasedMilliUnits + $quantityToAdd->milliUnits();

            if ($newPurchasedMilliUnits > StockQuantity::MAX_MILLI_UNITS) {
                throw ValidationException::withMessages([
                    'quantity_to_add' => "在庫「{$stock->name}」の月度仕入計が大きすぎます。",
                ]);
            }

            $newBalanceMilliUnits = StockQuantity::fromDecimal($stock->current_quantity)->milliUnits()
                + $quantityToAdd->milliUnits();

            $this->ensureBalanceWithinRange($stock, $newBalanceMilliUnits, 'quantity_to_add');

            $purchase = $this->savePurchase(
                $purchase,
                $stock,
                $term,
                StockQuantity::fromMilliUnits($newPurchasedMilliUnits),
            );

            $stock->transactions()->create([
                'transaction_type' => StockTransactionType::StockIn,
                'quantity_delta' => $quantityToAdd->toDecimalString(),
                'balance_after' => StockQuantity::fromMilliUnits($newBalanceMilliUnits)->toDecimalString(),
                'source_type' => ScheduleStockSourceType::StockPurchase,
                'source_id' => $purchase->id,
                'stock_name_snapshot' => $stock->name,
                'description' => $description,
                'created_by' => $actor?->id,
            ]);

            $stock->forceFill([
                'current_quantity' => StockQuantity::fromMilliUnits($newBalanceMilliUnits)->toDecimalString(),
            ])->save();
        });
    }

    /**
     * @throws ValidationException
     */
    public function correct(
        int $stockId,
        StockTerm $term,
        string $quantity,
        ?User $actor,
        ?string $description = null,
    ): void {
        DB::transaction(function () use ($stockId, $term, $quantity, $actor, $description): void {
            $stock = Stock::query()->lockForUpdate()->findOrFail($stockId);

            $quantityToSubtract = $this->positiveQuantity($stock, $quantity, 'quantity_to_subtract');
            $purchase = $this->lockedPurchase($stock, $term);
            $purchasedMilliUnits = $purchase instanceof StockPurchase
                ? StockQuantity::fromDecimal($purchase->quantity)->milliUnits()
                : 0;

            $newPurchasedMilliUnits = $purchasedMilliUnits - $quantityToSubtract->milliUnits();
            $newBalanceMilliUnits = StockQuantity::fromDecimal($stock->current_quantity)->milliUnits()
                - $quantityToSubtract->milliUnits();

            $this->ensurePurchaseWithinRange($stock, $newPurchasedMilliUnits, 'quantity_to_subtract');
            $this->ensureBalanceWithinRange($stock, $newBalanceMilliUnits, 'quantity_to_subtract');

            $purchase = $this->savePurchase(
                $purchase,
                $stock,
                $term,
                StockQuantity::fromMilliUnits($newPurchasedMilliUnits),
            );

            $stock->transactions()->create([
                'transaction_type' => StockTransactionType::Correction,
                'quantity_delta' => $quantityToSubtract->negated()->toDecimalString(),
                'balance_after' => StockQuantity::fromMilliUnits($newBalanceMilliUnits)->toDecimalString(),
                'source_type' => ScheduleStockSourceType::StockPurchase,
                'source_id' => $purchase->id,
                'stock_name_snapshot' => $stock->name,
                'description' => $description,
                'created_by' => $actor?->id,
            ]);

            $stock->forceFill([
                'current_quantity' => StockQuantity::fromMilliUnits($newBalanceMilliUnits)->toDecimalString(),
            ])->save();
        });
    }

    /**
     * @throws ValidationException
     */
    private function positiveQuantity(Stock $stock, string $quantity, string $field): StockQuantity
    {
        $stockQuantity = StockQuantity::tryFromDecimal($quantity);

        if (! $stockQuantity instanceof StockQuantity || ! $stockQuantity->isPositive()) {
            throw ValidationException::withMessages([
                $field => "在庫「{$stock->name}」の数量は0より大きい値を入力してください。",
            ]);
        }

        if (! $stock->allows_fractional_quantity && ! $stockQuantity->isWhole()) {
            throw ValidationException::withMessages([
                $field => "在庫「{$stock->name}」は整数のみ入力できます。",
            ]);
        }

        return $stockQuantity;
    }

    /**
     * @throws ValidationException
     */
    private function ensureBalanceWithinRange(Stock $stock, int $balanceMilliUnits, string $field): void
    {
        if (abs($balanceMilliUnits) > StockQuantity::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                $field => "在庫「{$stock->name}」の在庫数が扱える範囲を超えます。",
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensurePurchaseWithinRange(Stock $stock, int $purchasedMilliUnits, string $field): void
    {
        if (abs($purchasedMilliUnits) > StockQuantity::MAX_MILLI_UNITS) {
            throw ValidationException::withMessages([
                $field => "在庫「{$stock->name}」の月度仕入計が扱える範囲を超えます。",
            ]);
        }
    }

    private function lockedPurchase(Stock $stock, StockTerm $term): ?StockPurchase
    {
        return StockPurchase::query()
            ->whereBelongsTo($stock)
            ->whereDate('term_starts_on', $term->startsOn()->toDateString())
            ->lockForUpdate()
            ->first();
    }

    private function savePurchase(
        ?StockPurchase $purchase,
        Stock $stock,
        StockTerm $term,
        StockQuantity $quantity,
    ): StockPurchase {
        if (! $purchase instanceof StockPurchase) {
            return StockPurchase::query()->create([
                'stock_id' => $stock->id,
                'term_starts_on' => $term->startsOn()->toDateString(),
                'quantity' => $quantity->toDecimalString(),
            ]);
        }

        $purchase->forceFill([
            'quantity' => $quantity->toDecimalString(),
        ])->save();

        return $purchase;
    }
}
