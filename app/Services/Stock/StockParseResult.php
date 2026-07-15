<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\StockMentionStatus;

final readonly class StockParseResult
{
    /**
     * @param  list<ParsedStockMention>  $mentions
     */
    public function __construct(
        public array $mentions,
    ) {}

    public function hasIgnoredMentions(): bool
    {
        foreach ($this->mentions as $mention) {
            if ($mention->status !== StockMentionStatus::Recognized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aggregate desired usage per stock in milli-units (thousandths).
     * Inactive-stock mentions participate so that reconciliation can compare
     * them against previously applied usage (increase is rejected there).
     *
     * @return array<int, int> stock id => usage in milli-units
     */
    public function aggregateUsageMilliUnits(): array
    {
        $totals = [];

        foreach ($this->mentions as $mention) {
            if ($mention->quantityMilliUnits === null) {
                continue;
            }

            if (! in_array($mention->status, [StockMentionStatus::Recognized, StockMentionStatus::InactiveStock], true)) {
                continue;
            }

            $totals[$mention->stockId] = ($totals[$mention->stockId] ?? 0) + $mention->quantityMilliUnits;
        }

        return $totals;
    }
}
