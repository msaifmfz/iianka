<?php

declare(strict_types=1);

namespace App\Application\Stock;

use App\Domain\Stock\ValueObjects\StockTerm;
use App\Models\Stock;
use App\Models\StockPurchase;
use Illuminate\Support\Facades\DB;

class StockTermMemoUpdater
{
    public function update(int $stockId, StockTerm $term, ?string $memo): void
    {
        DB::transaction(function () use ($stockId, $term, $memo): void {
            $stock = Stock::query()->lockForUpdate()->findOrFail($stockId);

            $normalizedMemo = $memo === null ? null : trim($memo);
            $normalizedMemo = $normalizedMemo === '' ? null : $normalizedMemo;

            $purchase = StockPurchase::query()
                ->whereBelongsTo($stock)
                ->whereDate('term_starts_on', $term->startsOn()->toDateString())
                ->lockForUpdate()
                ->first();

            if ($purchase === null) {
                if ($normalizedMemo === null) {
                    return;
                }

                StockPurchase::query()->create([
                    'stock_id' => $stock->id,
                    'term_starts_on' => $term->startsOn()->toDateString(),
                    'quantity' => '0.000',
                    'memo' => $normalizedMemo,
                ]);

                return;
            }

            if ($purchase->memo === $normalizedMemo) {
                return;
            }

            $purchase->forceFill([
                'memo' => $normalizedMemo,
            ])->save();
        });
    }
}
