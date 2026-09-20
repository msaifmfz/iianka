<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Crm\Enums\ClientPlaceLogType;
use App\Domain\Crm\Enums\ClientReaction;
use Carbon\CarbonImmutable;
use Database\Factories\ClientPlaceLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry in a place's history: what staff did, what was discussed,
 * and how the client reacted.
 *
 * @property int $id
 * @property int $client_place_id
 * @property int|null $user_id
 * @property ClientPlaceLogType $type
 * @property CarbonImmutable $occurred_at
 * @property string $summary
 * @property ClientReaction|null $reaction
 */
#[Fillable([
    'client_place_id',
    'user_id',
    'client_contact_id',
    'type',
    'occurred_at',
    'summary',
    'reaction',
])]
class ClientPlaceLog extends Model
{
    /** @use HasFactory<ClientPlaceLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientPlaceLogType::class,
            'reaction' => ClientReaction::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClientPlace, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(ClientPlace::class, 'client_place_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ClientContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }

    /**
     * @return HasMany<ClientPlaceLogAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ClientPlaceLogAttachment::class);
    }

    /**
     * Authors own their entries; admins may correct anyone's.
     */
    public function isEditableBy(User $user): bool
    {
        return $user->isAdmin() || $this->user_id === $user->id;
    }
}
