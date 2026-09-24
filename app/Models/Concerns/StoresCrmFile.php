<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A CRM file kept on a private disk and served only through an
 * authenticated route: a history attachment or a client document.
 *
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property string|null $extension
 */
trait StoresCrmFile
{
    /**
     * Raster images the browser renders natively.
     *
     * @var list<string>
     */
    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

    /**
     * Office files, PDF and plain text. Only PDF opens in the browser; the
     * rest are always downloaded.
     *
     * @var list<string>
     */
    public const array DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function isPdf(): bool
    {
        return Str::lower((string) $this->extension) === 'pdf';
    }

    /**
     * Whether the file may be shown in the browser rather than downloaded.
     * Office and text files never are: only content the browser renders
     * without running anything of its own is served inline.
     */
    public function opensInline(): bool
    {
        return ! in_array(Str::lower((string) $this->extension), self::DOCUMENT_EXTENSIONS, true) || $this->isPdf();
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
