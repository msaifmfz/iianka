<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer tracked on the CRM map. Legacy colors remain stored but are
 * no longer displayed or edited; map colors identify staff instead.
 *
 * @property int $id
 * @property string $name
 * @property string $short_label
 * @property string $color
 * @property string|null $note
 */
#[Fillable([
    'name',
    'short_label',
    'color',
    'note',
    'created_by_user_id',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * @return HasMany<ClientPlace, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(ClientPlace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
