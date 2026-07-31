<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string|null $sku
 * @property string $name
 * @property string $normalized_name
 * @property string $current_quantity
 * @property bool $allows_fractional_quantity
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Collection<int, StockAlias> $aliases
 */
#[Fillable([
    'sku',
    'name',
    'normalized_name',
    'allows_fractional_quantity',
    'is_active',
    'sort_order',
])]
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    public const int SORT_ORDER_STEP = 10;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:3',
            'allows_fractional_quantity' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<StockAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(StockAlias::class);
    }

    /**
     * @return HasMany<StockTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    /**
     * @return HasMany<StockPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(StockPurchase::class);
    }
}
