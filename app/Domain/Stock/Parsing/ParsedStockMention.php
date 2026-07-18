<?php

declare(strict_types=1);

namespace App\Domain\Stock\Parsing;

use App\Domain\Stock\Enums\StockIdentificationMethod;
use App\Domain\Stock\Enums\StockMentionStatus;
use App\Domain\Stock\ValueObjects\StockQuantity;

/**
 * A stock mention found in schedule content. Offsets are Unicode code-point
 * offsets into the original content string. $quantity is null when the parsed
 * number cannot be represented as decimal(12,3).
 */
final readonly class ParsedStockMention
{
    public function __construct(
        public int $stockId,
        public string $stockNameSnapshot,
        public string $matchedText,
        public ?StockQuantity $quantity,
        public int $startOffset,
        public int $endOffset,
        public ?int $quantityStartOffset,
        public ?int $quantityEndOffset,
        public StockIdentificationMethod $identificationMethod,
        public StockMentionStatus $status,
    ) {}
}
