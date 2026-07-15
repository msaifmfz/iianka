<?php

declare(strict_types=1);

namespace App\Models;

use App\ScheduleStockSourceType;
use App\StockIdentificationMethod;
use App\StockMentionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $revision_id
 * @property ScheduleStockSourceType $schedule_type
 * @property int $schedule_id
 * @property int $stock_id
 * @property string $stock_name_snapshot
 * @property string $matched_text
 * @property string|null $quantity
 * @property int $start_offset
 * @property int $end_offset
 * @property int|null $quantity_start_offset
 * @property int|null $quantity_end_offset
 * @property StockIdentificationMethod $identification_method
 * @property StockMentionStatus $status
 */
#[Fillable([
    'revision_id',
    'schedule_type',
    'schedule_id',
    'stock_id',
    'stock_name_snapshot',
    'matched_text',
    'quantity',
    'start_offset',
    'end_offset',
    'quantity_start_offset',
    'quantity_end_offset',
    'identification_method',
    'status',
])]
class ScheduleStockMention extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'schedule_type' => ScheduleStockSourceType::class,
            'schedule_id' => 'integer',
            'quantity' => 'decimal:3',
            'start_offset' => 'integer',
            'end_offset' => 'integer',
            'quantity_start_offset' => 'integer',
            'quantity_end_offset' => 'integer',
            'identification_method' => StockIdentificationMethod::class,
            'status' => StockMentionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ScheduleContentRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ScheduleContentRevision::class, 'revision_id');
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
