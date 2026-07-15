<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\StockIdentificationMethod;
use App\StockMentionStatus;

/**
 * A stock mention found in schedule content. Offsets are Unicode code-point
 * offsets into the original content string. $quantity is the canonical
 * decimal string (e.g. "2.000"); it is null when the parsed number cannot be
 * represented as decimal(12,3). $quantityMilliUnits is non-null only when the
 * quantity is valid (positive and within scale) and is expressed in
 * thousandths to keep aggregation exact.
 */
final readonly class ParsedStockMention
{
    public function __construct(
        public int $stockId,
        public string $stockNameSnapshot,
        public string $matchedText,
        public ?string $quantity,
        public ?int $quantityMilliUnits,
        public int $startOffset,
        public int $endOffset,
        public ?int $quantityStartOffset,
        public ?int $quantityEndOffset,
        public StockIdentificationMethod $identificationMethod,
        public StockMentionStatus $status,
    ) {}
}
