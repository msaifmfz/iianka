<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $input
 * @property array<string, mixed> $calculation
 * @property array<string, array<string, mixed>> $catalog_snapshot
 * @property array<string, string> $issuer
 */
#[Fillable(['number', 'client_name', 'project_name', 'issued_on', 'input', 'calculation', 'catalog_snapshot', 'issuer', 'created_by', 'version'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['input' => 'array', 'calculation' => 'array', 'catalog_snapshot' => 'array', 'issuer' => 'array', 'issued_on' => 'date', 'version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
