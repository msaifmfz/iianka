<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Crm\Enums\ClientPlaceKind;
use Carbon\CarbonImmutable;
use Database\Factories\ClientPlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A pin on the CRM map: a location where things happen with one client.
 *
 * @property int $id
 * @property int $client_id
 * @property ClientPlaceKind $kind
 * @property string $name
 * @property string|null $address
 * @property string $lat
 * @property string $lng
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $last_logged_at
 */
#[Fillable([
    'client_id',
    'kind',
    'name',
    'address',
    'lat',
    'lng',
    'archived_at',
    'last_logged_at',
    'created_by_user_id',
])]
class ClientPlace extends Model
{
    /** @use HasFactory<ClientPlaceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ClientPlaceKind::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'archived_at' => 'datetime',
            'last_logged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<ClientPlaceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ClientPlaceLog::class);
    }

    /**
     * @return HasOne<ClientPlaceLog, $this>
     */
    public function latestActivity(): HasOne
    {
        return $this->hasOne(ClientPlaceLog::class)->ofMany([
            'occurred_at' => 'max',
            'id' => 'max',
        ]);
    }

    /**
     * Read-only summaries: one date per author across every activity type.
     *
     * @return HasMany<ClientPlaceLog, $this>
     */
    public function staffActivitySummaries(): HasMany
    {
        return $this->logs()
            ->select(['client_place_id', 'user_id'])
            ->selectRaw('MAX(occurred_at) as occurred_at')
            ->groupBy('client_place_id', 'user_id')
            ->orderBy('user_id');
    }

    /**
     * @param  Builder<ClientPlace>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Recompute the denormalized `last_logged_at` the map uses to fade stale
     * pins, after any history entry is added, re-dated or removed.
     */
    public function refreshLastLoggedAt(): void
    {
        $latest = $this->logs()->max('occurred_at');

        $this->forceFill(['last_logged_at' => $latest])->save();
    }
}
