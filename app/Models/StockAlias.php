<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StockAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $stock_id
 * @property string $alias
 * @property string $normalized_alias
 * @property bool $is_active
 */
#[Fillable([
    'stock_id',
    'alias',
    'normalized_alias',
    'is_active',
])]
class StockAlias extends Model
{
    /** @use HasFactory<StockAliasFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
