<?php

declare(strict_types=1);

namespace App\Models;

use App\ScheduleStockSourceType;
use App\StockTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Immutable inventory ledger entry. Never update or delete rows; corrections
 * are represented as new transactions.
 *
 * @property int $id
 * @property int $stock_id
 * @property StockTransactionType $transaction_type
 * @property string $quantity_delta
 * @property string $balance_after
 * @property ScheduleStockSourceType|null $source_type
 * @property int|null $source_id
 * @property int|null $source_revision_id
 * @property int|null $source_mention_id
 * @property string $stock_name_snapshot
 * @property string|null $description
 * @property int|null $created_by
 * @property int|null $reversal_of_transaction_id
 */
#[Fillable([
    'stock_id',
    'transaction_type',
    'quantity_delta',
    'balance_after',
    'source_type',
    'source_id',
    'source_revision_id',
    'source_mention_id',
    'stock_name_snapshot',
    'description',
    'created_by',
    'reversal_of_transaction_id',
])]
class StockTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'transaction_type' => StockTransactionType::class,
            'quantity_delta' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'source_type' => ScheduleStockSourceType::class,
            'source_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
