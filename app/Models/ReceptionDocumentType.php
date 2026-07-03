<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReceptionDocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

#[Fillable(['name', 'sort_order', 'is_active'])]
class ReceptionDocumentType extends Model
{
    /** @use HasFactory<ReceptionDocumentTypeFactory> */
    use HasFactory;

    /**
     * Spacing between consecutive `sort_order` values, leaving gaps for
     * inserts and keeping reorder positions evenly distributed.
     */
    public const int SORT_ORDER_STEP = 10;

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

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<ReceptionCase, $this>
     */
    public function receptionCases(): HasMany
    {
        return $this->hasMany(ReceptionCase::class);
    }
}
