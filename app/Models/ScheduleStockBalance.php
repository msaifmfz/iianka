<?php

declare(strict_types=1);

namespace App\Models;

use App\ScheduleStockSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property ScheduleStockSourceType $schedule_type
 * @property int $schedule_id
 * @property int $stock_id
 * @property string $applied_quantity
 * @property int|null $latest_revision_id
 */
#[Fillable([
    'schedule_type',
    'schedule_id',
    'stock_id',
    'applied_quantity',
    'latest_revision_id',
])]
class ScheduleStockBalance extends Model
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
            'applied_quantity' => 'decimal:3',
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
