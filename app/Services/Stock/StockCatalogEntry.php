<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\StockIdentificationMethod;

/**
 * One matchable catalog text (a stock's current name or one of its aliases).
 */
final readonly class StockCatalogEntry
{
    public function __construct(
        public int $stockId,
        public string $stockName,
        public string $normalizedMatchText,
        public StockIdentificationMethod $identificationMethod,
        public bool $isActive,
        public bool $allowsFractionalQuantity,
    ) {}
}
