<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\StoresCrmFile;
use Carbon\CarbonImmutable;
use Database\Factories\ClientDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A file kept on the client itself rather than on one visit: a quote,
 * contract, invoice, drawing or a scan of one. May point at the place it
 * concerns; it stays with the client when that place is deleted.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $client_place_id
 * @property int|null $uploaded_by_user_id
 * @property string $name
 * @property CarbonImmutable|null $issued_on
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int|null $size
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'client_id',
    'client_place_id',
    'uploaded_by_user_id',
    'name',
    'issued_on',
    'disk',
    'path',
    'mime_type',
    'extension',
    'size',
])]
class ClientDocument extends Model
{
    /** @use HasFactory<ClientDocumentFactory> */
    use HasFactory;

    use StoresCrmFile;

    public const string DISK = 'local';

    public const int MAX_FILE_KILOBYTES = 50 * 1024;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'size' => 'integer',
        ];
    }

    /**
     * Documents plus scans and photos of paper ones; no audio.
     *
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return [...self::DOCUMENT_EXTENSIONS, ...self::IMAGE_EXTENSIONS];
    }

    /**
     * Newest first by the date on the document, or by upload day when it
     * has none.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->orderByRaw('coalesce(issued_on, date(created_at)) desc')->latest('id');
    }

    /**
     * Filed on the given place or on the client as a whole.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function relevantTo(Builder $query, ClientPlace $place): void
    {
        $query->whereBelongsTo($place->client)
            ->where(fn (Builder $query) => $query->whereNull('client_place_id')->orWhereBelongsTo($place, 'place'));
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Uploaders remove their own documents; admins may remove anyone's.
     */
    public function isDeletableBy(User $user): bool
    {
        return $user->isAdmin() || $this->uploaded_by_user_id === $user->id;
    }

    public function url(): string
    {
        return route('crm.documents.show', $this);
    }
}
