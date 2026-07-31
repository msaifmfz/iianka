<?php

declare(strict_types=1);

namespace App\Application\Stock;

use App\Domain\Stock\Enums\ScheduleStockSourceType;
use App\Domain\Stock\Enums\StockExtractionStatus;
use App\Domain\Stock\Enums\StockIdentificationMethod;
use App\Domain\Stock\Enums\StockTransactionType;
use App\Domain\Stock\Parsing\ScheduleContentStockParser;
use App\Domain\Stock\Parsing\StockCatalogEntry;
use App\Domain\Stock\ValueObjects\StockQuantity;
use App\Models\ConstructionSchedule;
use App\Models\ScheduleContentRevision;
use App\Models\ScheduleStockBalance;
use App\Models\Stock;
use App\Models\StockAlias;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reconciles the stock usage extracted from schedule content against the
 * usage already applied for that schedule, recording immutable ledger
 * transactions for every inventory change.
 *
 * Each public operation owns its transaction. Stock rows are always locked in
 * ascending id order to avoid deadlocks.
 */
class ScheduleStockReconciliationService
{
    public function __construct(
        private readonly ScheduleContentStockParser $parser,
    ) {}

    /**
     * Parse the schedule's current content and apply the resulting stock
     * deltas. No-op when both the content hash and parser version are current.
     *
     * @throws ValidationException
     */
    public function reconcile(ConstructionSchedule $schedule, ?User $actor, ?int $expectedContentVersion = null): void
    {
        DB::transaction(function () use ($schedule, $actor, $expectedContentVersion): void {
            $this->reconcileWithinTransaction($schedule, $actor, $expectedContentVersion);
        });
    }

    private function reconcileWithinTransaction(ConstructionSchedule $schedule, ?User $actor, ?int $expectedContentVersion): void
    {
        $sourceType = ScheduleStockSourceType::ConstructionSchedule;

        $locked = ConstructionSchedule::query()->lockForUpdate()->findOrFail($schedule->id);

        $content = $locked->content ?? '';
        $contentHash = hash('sha256', $content);

        if (
            $contentHash === $locked->content_hash
            && $locked->stock_parser_version === ScheduleContentStockParser::VERSION
        ) {
            return;
        }

        if ($expectedContentVersion !== null && $expectedContentVersion !== $locked->content_version) {
            throw ValidationException::withMessages([
                'content' => 'この予定は他のユーザーによって更新されました。ページを再読み込みして最新の内容を確認してください。',
            ]);
        }

        $result = $this->parser->parse($content, $this->loadCatalogEntries());
        $desiredTotals = $result->aggregateUsageMilliUnits();

        $balances = ScheduleStockBalance::query()
            ->where('schedule_type', $sourceType)
            ->where('schedule_id', $locked->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('stock_id');

        $appliedTotals = $balances
            ->toBase()
            ->map(fn (ScheduleStockBalance $balance): int => StockQuantity::fromDecimal($balance->applied_quantity)->milliUnits())
            ->all();

        $stockIds = array_values(array_unique([...array_keys($desiredTotals), ...array_keys($appliedTotals)]));
        sort($stockIds);

        $stocks = $this->lockStocks($stockIds);

        $hasDeltas = false;

        foreach ($stockIds as $stockId) {
            $desired = $desiredTotals[$stockId] ?? 0;
            $applied = $appliedTotals[$stockId] ?? 0;

            if ($desired === $applied) {
                continue;
            }

            $hasDeltas = true;
            $stock = $stocks[$stockId];

            if (! $stock->is_active && $desired > $applied) {
                throw ValidationException::withMessages([
                    'content' => "在庫「{$stock->name}」は無効化されているため、使用数を増やせません。",
                ]);
            }

            if ($desired > StockQuantity::MAX_MILLI_UNITS) {
                throw ValidationException::withMessages([
                    'content' => "在庫「{$stock->name}」の使用数が大きすぎます。",
                ]);
            }

            $projectedBalance = StockQuantity::fromDecimal($stock->current_quantity)->milliUnits() - ($desired - $applied);

            if ($projectedBalance > StockQuantity::MAX_MILLI_UNITS || $projectedBalance < -StockQuantity::MAX_MILLI_UNITS) {
                throw ValidationException::withMessages([
                    'content' => "在庫「{$stock->name}」の在庫数が扱える範囲を超えます。",
                ]);
            }
        }

        $revision = null;

        if ($result->mentions !== [] || $hasDeltas) {
            $revision = ScheduleContentRevision::query()->create([
                'schedule_type' => $sourceType,
                'schedule_id' => $locked->id,
                'content_hash' => $contentHash,
                'content_snapshot' => $content,
                'parser_version' => ScheduleContentStockParser::VERSION,
                'created_by' => $actor?->id,
            ]);

            foreach ($result->mentions as $mention) {
                $revision->mentions()->create([
                    'schedule_type' => $sourceType,
                    'schedule_id' => $locked->id,
                    'stock_id' => $mention->stockId,
                    'stock_name_snapshot' => $mention->stockNameSnapshot,
                    'matched_text' => $mention->matchedText,
                    'quantity' => $mention->quantity?->toDecimalString(),
                    'start_offset' => $mention->startOffset,
                    'end_offset' => $mention->endOffset,
                    'quantity_start_offset' => $mention->quantityStartOffset,
                    'quantity_end_offset' => $mention->quantityEndOffset,
                    'identification_method' => $mention->identificationMethod,
                    'status' => $mention->status,
                ]);
            }
        }

        foreach ($stockIds as $stockId) {
            $desired = $desiredTotals[$stockId] ?? 0;
            $applied = $appliedTotals[$stockId] ?? 0;
            $usageDelta = $desired - $applied;

            if ($usageDelta === 0) {
                continue;
            }

            $stock = $stocks[$stockId];
            $newQuantity = StockQuantity::fromDecimal($stock->current_quantity)->milliUnits() - $usageDelta;

            $stock->transactions()->create([
                'transaction_type' => $usageDelta > 0 ? StockTransactionType::ScheduleStockOut : StockTransactionType::ScheduleReversal,
                'quantity_delta' => StockQuantity::fromMilliUnits(-$usageDelta)->toDecimalString(),
                'balance_after' => StockQuantity::fromMilliUnits($newQuantity)->toDecimalString(),
                'source_type' => $sourceType,
                'source_id' => $locked->id,
                'source_revision_id' => $revision?->id,
                'stock_name_snapshot' => $stock->name,
                'created_by' => $actor?->id,
            ]);

            $stock->forceFill([
                'current_quantity' => StockQuantity::fromMilliUnits($newQuantity)->toDecimalString(),
            ])->save();
        }

        foreach ($stockIds as $stockId) {
            $desired = $desiredTotals[$stockId] ?? 0;

            if ($desired === 0) {
                $balances->get($stockId)?->delete();

                continue;
            }

            ScheduleStockBalance::query()->updateOrCreate(
                [
                    'schedule_type' => $sourceType,
                    'schedule_id' => $locked->id,
                    'stock_id' => $stockId,
                ],
                [
                    'applied_quantity' => StockQuantity::fromMilliUnits($desired)->toDecimalString(),
                    'latest_revision_id' => $revision?->id,
                ],
            );
        }

        $locked->forceFill([
            'content_hash' => $contentHash,
            'content_version' => $locked->content_version + 1,
            'stock_parser_version' => ScheduleContentStockParser::VERSION,
            'stock_extraction_status' => $result->hasIgnoredMentions()
                ? StockExtractionStatus::ProcessedWithIgnoredText
                : StockExtractionStatus::Processed,
            'stock_extracted_at' => now(),
        ])->save();
    }

    /**
     * Reverse all applied stock usage for a schedule that is about to be
     * deleted. Revisions, mentions, and ledger transactions are kept as audit
     * history.
     */
    public function releaseFor(ConstructionSchedule $schedule, ?User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $this->releaseWithinTransaction($schedule, $actor);
        });
    }

    private function releaseWithinTransaction(ConstructionSchedule $schedule, ?User $actor): void
    {
        $sourceType = ScheduleStockSourceType::ConstructionSchedule;

        $balances = ScheduleStockBalance::query()
            ->where('schedule_type', $sourceType)
            ->where('schedule_id', $schedule->id)
            ->orderBy('stock_id')
            ->lockForUpdate()
            ->get();

        if ($balances->isEmpty()) {
            return;
        }

        $stocks = $this->lockStocks($balances->pluck('stock_id')->all());

        foreach ($balances as $balance) {
            $applied = StockQuantity::fromDecimal($balance->applied_quantity)->milliUnits();

            if ($applied !== 0) {
                $stock = $stocks[$balance->stock_id];
                $newQuantity = StockQuantity::fromDecimal($stock->current_quantity)->milliUnits() + $applied;

                if ($newQuantity > StockQuantity::MAX_MILLI_UNITS || $newQuantity < -StockQuantity::MAX_MILLI_UNITS) {
                    throw ValidationException::withMessages([
                        'content' => "在庫「{$stock->name}」の在庫数が扱える範囲を超えます。",
                    ]);
                }

                $stock->transactions()->create([
                    'transaction_type' => StockTransactionType::ScheduleReversal,
                    'quantity_delta' => StockQuantity::fromMilliUnits($applied)->toDecimalString(),
                    'balance_after' => StockQuantity::fromMilliUnits($newQuantity)->toDecimalString(),
                    'source_type' => $sourceType,
                    'source_id' => $schedule->id,
                    'source_revision_id' => $balance->latest_revision_id,
                    'stock_name_snapshot' => $stock->name,
                    'description' => '予定削除による戻し',
                    'created_by' => $actor?->id,
                ]);

                $stock->forceFill([
                    'current_quantity' => StockQuantity::fromMilliUnits($newQuantity)->toDecimalString(),
                ])->save();
            }

            $balance->delete();
        }
    }

    /**
     * @return list<StockCatalogEntry>
     */
    private function loadCatalogEntries(): array
    {
        $stocks = Stock::query()->get();
        $stocksById = $stocks->keyBy('id');

        $entries = [];

        foreach ($stocks as $stock) {
            $entries[] = new StockCatalogEntry(
                stockId: $stock->id,
                stockName: $stock->name,
                normalizedMatchText: $stock->normalized_name,
                identificationMethod: StockIdentificationMethod::CurrentName,
                isActive: $stock->is_active,
                allowsFractionalQuantity: $stock->allows_fractional_quantity,
            );
        }

        foreach (StockAlias::query()->where('is_active', true)->get() as $alias) {
            $stock = $stocksById->get($alias->stock_id);

            if ($stock === null) {
                continue;
            }

            $entries[] = new StockCatalogEntry(
                stockId: $stock->id,
                stockName: $stock->name,
                normalizedMatchText: $alias->normalized_alias,
                identificationMethod: StockIdentificationMethod::Alias,
                isActive: $stock->is_active,
                allowsFractionalQuantity: $stock->allows_fractional_quantity,
            );
        }

        return $entries;
    }

    /**
     * @param  list<int>  $stockIds
     * @return Collection<int, Stock>
     */
    private function lockStocks(array $stockIds): Collection
    {
        if ($stockIds === []) {
            return new Collection;
        }

        return Stock::query()
            ->whereIn('id', $stockIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }
}
