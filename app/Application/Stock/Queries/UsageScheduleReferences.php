<?php

declare(strict_types=1);

namespace App\Application\Stock\Queries;

use App\Domain\Stock\ValueObjects\StockTerm;

/**
 * Which construction schedules consumed a stock, grouped per stock and per
 * usage bucket. Only the schedule id and quantity live here; the schedule's
 * own details are loaded separately so the report rows stay small.
 */
final readonly class UsageScheduleReferences
{
    /**
     * @param  array<int, array<string, list<array{schedule_id: int, quantity: string}>>>  $byStockId  stock id => bucket start date => references
     */
    public function __construct(private array $byStockId) {}

    /**
     * References for one stock across a term, always keyed by every one of the
     * term's bucket start dates so the payload shape does not depend on which
     * buckets happen to have usage.
     *
     * @return array<string, list<array{schedule_id: int, quantity: string}>>
     */
    public function forTerm(int $stockId, StockTerm $term): array
    {
        $stockReferences = $this->byStockId[$stockId] ?? [];
        $references = [];

        foreach ($term->buckets() as $bucket) {
            $bucketStart = $bucket['starts_on']->toDateString();
            $references[$bucketStart] = $stockReferences[$bucketStart] ?? [];
        }

        return $references;
    }
}
