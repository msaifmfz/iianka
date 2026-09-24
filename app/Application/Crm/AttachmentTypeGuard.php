<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\ClientPlaceLogAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Decides whether an upload really is the photo, voice memo or document its
 * name claims. Photos, audio and PDFs are served inline from the app origin,
 * so the sniffed content — not the extension — has the final say.
 */
final readonly class AttachmentTypeGuard
{
    /**
     * @var list<string>
     */
    private const array IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
    ];

    /**
     * MediaRecorder output is often labelled as video/* even when it only
     * carries an audio track.
     *
     * @var list<string>
     */
    private const array AUDIO_CONTAINER_MIME_TYPES = ['video/webm', 'video/mp4'];

    /**
     * What libmagic reports for each document extension. Legacy Office files
     * are often only recognised as a generic OLE compound document.
     *
     * @var array<string, list<string>>
     */
    private const array DOCUMENT_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/cdfv2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/cdfv2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/cdfv2'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
    ];

    /**
     * @var list<string>
     */
    private const array HEIF_EXTENSIONS = ['heic', 'heif'];

    /**
     * ISO base media brands used by HEIF stills and sequences.
     *
     * @var list<string>
     */
    private const array HEIF_BRANDS = [
        'heic', 'heix', 'heim', 'heis',
        'hevc', 'hevx', 'hevm', 'hevs',
        'mif1', 'msf1',
    ];

    public function accepts(UploadedFile $file): bool
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $mimeType = Str::lower((string) $file->getMimeType());

        if (in_array($extension, ClientPlaceLogAttachment::IMAGE_EXTENSIONS, true)) {
            return in_array($mimeType, self::IMAGE_MIME_TYPES, true)
                || (in_array($extension, self::HEIF_EXTENSIONS, true)
                    && $this->looksLikeHeif($file->getPathname()));
        }

        if (in_array($extension, ClientPlaceLogAttachment::AUDIO_EXTENSIONS, true)) {
            return Str::startsWith($mimeType, 'audio/')
                || in_array($mimeType, self::AUDIO_CONTAINER_MIME_TYPES, true);
        }

        if (array_key_exists($extension, self::DOCUMENT_MIME_TYPES)) {
            return in_array($mimeType, self::DOCUMENT_MIME_TYPES[$extension], true);
        }

        return false;
    }

    /**
     * Whether a file carries a HEIF still or sequence header.
     *
     * Only some libmagic builds name HEIC/HEIF; the rest report
     * application/octet-stream, which would reject the photos an iPhone
     * actually produces. Read the ISO base media header instead: bytes 4-8 are
     * the literal `ftyp` and the four that follow are the major brand. Public
     * so the fallback can be tested on hosts whose libmagic never needs it.
     */
    public function looksLikeHeif(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $header = file_get_contents($path, false, null, 0, 12);

        return is_string($header)
            && strlen($header) === 12
            && substr($header, 4, 4) === 'ftyp'
            && in_array(Str::lower(substr($header, 8, 4)), self::HEIF_BRANDS, true);
    }
}
