<?php

declare(strict_types=1);

namespace App\Models;

use App\ReceptionCaseAttachmentKind;
use App\ReceptionCaseAttachmentPreviewMode;
use App\ReceptionCaseAttachmentSource;
use Database\Factories\ReceptionCaseAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Override;
use RuntimeException;

/**
 * @property int $id
 * @property ReceptionCaseAttachmentKind $kind
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property string|null $extension
 */
#[Fillable([
    'reception_case_id',
    'uploaded_by_user_id',
    'kind',
    'source',
    'name',
    'disk',
    'path',
    'mime_type',
    'extension',
    'size',
    'duration_seconds',
])]
class ReceptionCaseAttachment extends Model
{
    /** @use HasFactory<ReceptionCaseAttachmentFactory> */
    use HasFactory;

    public const string DISK = 'local';

    public const int MAX_ATTACHMENTS_PER_CASE = 20;

    public const int MAX_RECORDINGS_PER_CASE = 10;

    public const int MAX_RECORDING_SECONDS = 600;

    public const int MAX_FILE_KILOBYTES = 50 * 1024;

    /**
     * @var list<string>
     */
    public const DOCUMENT_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'csv',
        'zip',
    ];

    /**
     * @var list<string>
     */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

    /**
     * @var list<string>
     */
    public const AUDIO_EXTENSIONS = ['webm', 'm4a', 'mp3', 'wav', 'ogg'];

    /**
     * @var list<string>
     */
    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', '3gp', '3gpp', '3g2', 'webm'];

    /**
     * @var list<string>
     */
    private const array PREVIEW_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @var list<string>
     */
    private const array PREVIEW_VIDEO_EXTENSIONS = ['mp4', 'm4v', 'webm'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ReceptionCaseAttachmentKind::class,
            'source' => ReceptionCaseAttachmentSource::class,
            'size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ReceptionCase, $this>
     */
    public function receptionCase(): BelongsTo
    {
        return $this->belongsTo(ReceptionCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function url(): string
    {
        return route('reception.attachments.show', $this);
    }

    public function downloadUrl(): string
    {
        return route('reception.attachments.show', [
            'reception_case_attachment' => $this,
            'download' => 1,
        ]);
    }

    public function previewMode(): ReceptionCaseAttachmentPreviewMode
    {
        $extension = mb_strtolower((string) $this->extension);

        if (in_array($extension, self::PREVIEW_IMAGE_EXTENSIONS, true)) {
            return ReceptionCaseAttachmentPreviewMode::Image;
        }

        if ($extension === 'pdf') {
            return ReceptionCaseAttachmentPreviewMode::Pdf;
        }

        if (
            $this->kind === ReceptionCaseAttachmentKind::Video
            && in_array($extension, self::PREVIEW_VIDEO_EXTENSIONS, true)
        ) {
            return ReceptionCaseAttachmentPreviewMode::Video;
        }

        if ($this->kind === ReceptionCaseAttachmentKind::Audio || in_array($extension, self::AUDIO_EXTENSIONS, true)) {
            return ReceptionCaseAttachmentPreviewMode::Audio;
        }

        return ReceptionCaseAttachmentPreviewMode::Download;
    }

    public function canPreviewInline(): bool
    {
        return $this->previewMode() !== ReceptionCaseAttachmentPreviewMode::Download;
    }

    public function absolutePath(): string
    {
        $path = Storage::disk($this->disk)->path($this->path);

        if (! is_file($path)) {
            throw new RuntimeException("Reception attachment [{$this->id}] is missing from storage.");
        }

        return $path;
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * File name presented to the browser, always carrying the stored extension.
     */
    public function downloadName(): string
    {
        $extension = $this->extension;

        $name = $extension === null || $extension === '' || Str::endsWith(Str::lower($this->name), '.'.Str::lower($extension))
            ? $this->name
            : $this->name.'.'.$extension;

        // Path separators and control chars are illegal in a Content-Disposition
        // filename (Symfony rejects "/" and "\"); the stored name keeps them for the UI.
        return trim((string) preg_replace('#[/\\\\\x00-\x1f]+#', '-', $name)) ?: 'attachment';
    }

    /**
     * ASCII-safe fallback for the Content-Disposition header (RFC 6266).
     */
    public function asciiDownloadName(): string
    {
        $ascii = trim((string) preg_replace('/[^A-Za-z0-9._-]/', '', (string) Str::ascii($this->downloadName())), '.');

        if ($ascii === '') {
            $extension = $this->extension !== null && $this->extension !== ''
                ? '.'.$this->extension
                : '';

            return 'attachment'.$extension;
        }

        return $ascii;
    }

    public function deleteStoredFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return array_values(array_unique([
            ...self::DOCUMENT_EXTENSIONS,
            ...self::IMAGE_EXTENSIONS,
            ...self::AUDIO_EXTENSIONS,
            ...self::VIDEO_EXTENSIONS,
        ]));
    }

    public static function kindForExtension(
        string $extension,
        ?string $mimeType = null,
        ?ReceptionCaseAttachmentSource $source = null,
    ): ReceptionCaseAttachmentKind {
        $extension = mb_strtolower($extension);
        $mimeType = mb_strtolower((string) $mimeType);

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return ReceptionCaseAttachmentKind::Image;
        }

        if ($source === ReceptionCaseAttachmentSource::Recording) {
            return ReceptionCaseAttachmentKind::Audio;
        }

        if (
            in_array($extension, self::VIDEO_EXTENSIONS, true)
            && (str_starts_with($mimeType, 'video/') || ! in_array($extension, self::AUDIO_EXTENSIONS, true))
        ) {
            return ReceptionCaseAttachmentKind::Video;
        }

        if (in_array($extension, self::AUDIO_EXTENSIONS, true)) {
            return ReceptionCaseAttachmentKind::Audio;
        }

        return ReceptionCaseAttachmentKind::Document;
    }

    /**
     * @return list<string>
     */
    public static function allowedMimeTypesForExtension(string $extension, bool $isRecording = false): array
    {
        return match (mb_strtolower($extension)) {
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'heic' => ['image/heic', 'image/heic-sequence'],
            'heif' => ['image/heif', 'image/heif-sequence'],
            'mp4' => ['video/mp4', 'application/mp4'],
            'mov' => ['video/quicktime'],
            'm4v' => ['video/mp4', 'video/x-m4v', 'application/mp4'],
            '3gp', '3gpp' => ['video/3gpp'],
            '3g2' => ['video/3gpp2'],
            'webm' => ['audio/webm', 'video/webm'],
            'm4a' => $isRecording ? ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4'] : ['audio/mp4', 'audio/x-m4a'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            default => [],
        };
    }

    /**
     * The HTML file-input `accept` value: dotted extensions plus every allowed
     * MIME type. Kept here so the browser picker and server validation share one
     * source of truth.
     */
    public static function acceptAttribute(): string
    {
        $extensions = array_map(
            static fn (string $extension): string => '.'.$extension,
            self::allowedExtensions(),
        );

        return implode(',', [...$extensions, ...self::allowedMimeTypes()]);
    }

    /**
     * @return list<string>
     */
    public static function allowedMimeTypes(): array
    {
        $mimeTypes = [];

        foreach (self::allowedExtensions() as $extension) {
            $mimeTypes = [
                ...$mimeTypes,
                ...self::allowedMimeTypesForExtension($extension),
                ...self::allowedMimeTypesForExtension($extension, isRecording: true),
            ];
        }

        return array_values(array_unique($mimeTypes));
    }

    #[Override]
    protected static function booted(): void
    {
        self::deleted(function (ReceptionCaseAttachment $attachment): void {
            $attachment->deleteStoredFile();
        });
    }
}
