<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StockPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * Purchased quantity for one stock in one term month (21st to the 20th of
 * the following month). term_starts_on is always the 21st.
 *
 * @property int $id
 * @property int $stock_id
 * @property Carbon $term_starts_on
 * @property string $quantity
 */
#[Fillable([
    'stock_id',
    'term_starts_on',
    'quantity',
])]
class StockPurchase extends Model
{
    /** @use HasFactory<StockPurchaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'term_starts_on' => 'date',
            'quantity' => 'decimal:3',
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
