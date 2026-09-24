<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Crm\Enums\ClientPlaceLogAttachmentKind;
use App\Models\Concerns\StoresCrmFile;
use Database\Factories\ClientPlaceLogAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Override;

/**
 * A photo, voice memo or document attached to a CRM history entry. Stored
 * on the private disk and only served through an authenticated route.
 *
 * @property int $id
 * @property int $client_place_log_id
 * @property ClientPlaceLogAttachmentKind $kind
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property string|null $extension
 * @property int|null $duration_seconds
 */
#[Fillable([
    'client_place_log_id',
    'uploaded_by_user_id',
    'kind',
    'name',
    'disk',
    'path',
    'mime_type',
    'extension',
    'size',
    'duration_seconds',
])]
class ClientPlaceLogAttachment extends Model
{
    /** @use HasFactory<ClientPlaceLogAttachmentFactory> */
    use HasFactory;

    use StoresCrmFile;

    public const string DISK = 'local';

    public const int MAX_PER_LOG = 10;

    public const int MAX_FILE_KILOBYTES = 50 * 1024;

    public const int MAX_RECORDING_SECONDS = 600;

    /**
     * @var list<string>
     */
    public const array AUDIO_EXTENSIONS = ['webm', 'm4a', 'mp3', 'wav', 'ogg', 'mp4'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ClientPlaceLogAttachmentKind::class,
            'size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ClientPlaceLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(ClientPlaceLog::class, 'client_place_log_id');
    }

    public static function kindForExtension(string $extension): ClientPlaceLogAttachmentKind
    {
        $extension = Str::lower($extension);

        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => ClientPlaceLogAttachmentKind::Image,
            in_array($extension, self::DOCUMENT_EXTENSIONS, true) => ClientPlaceLogAttachmentKind::Document,
            default => ClientPlaceLogAttachmentKind::Audio,
        };
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return [...self::IMAGE_EXTENSIONS, ...self::AUDIO_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS];
    }

    public function url(): string
    {
        return route('crm.attachments.show', $this);
    }
}
