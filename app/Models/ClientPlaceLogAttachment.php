<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Crm\Enums\ClientPlaceLogAttachmentKind;
use Database\Factories\ClientPlaceLogAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Override;

/**
 * A photo or voice memo attached to a CRM history entry. Stored on the
 * private disk and only served through an authenticated route.
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

    public const string DISK = 'local';

    public const int MAX_PER_LOG = 10;

    public const int MAX_FILE_KILOBYTES = 50 * 1024;

    public const int MAX_RECORDING_SECONDS = 600;

    /**
     * @var list<string>
     */
    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

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
        return in_array(Str::lower($extension), self::IMAGE_EXTENSIONS, true)
            ? ClientPlaceLogAttachmentKind::Image
            : ClientPlaceLogAttachmentKind::Audio;
    }

    public function url(): string
    {
        return route('crm.attachments.show', $this);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * File name presented to the browser, always carrying the stored extension
     * and free of characters a Content-Disposition header rejects.
     */
    public function downloadName(): string
    {
        $extension = (string) $this->extension;

        $name = $extension === '' || Str::endsWith(Str::lower($this->name), '.'.Str::lower($extension))
            ? $this->name
            : $this->name.'.'.$extension;

        return trim((string) preg_replace('#[/\\\\\x00-\x1f]+#', '-', $name)) ?: 'attachment';
    }
}
