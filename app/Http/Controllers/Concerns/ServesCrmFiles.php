<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\ClientDocument;
use App\Models\ClientPlaceLogAttachment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

trait ServesCrmFiles
{
    /**
     * Images and audio open inline under a sandbox policy so no script can run
     * even if something slipped past validation. PDFs open inline without it:
     * browsers refuse to start their PDF viewer in a sandboxed document, and
     * the viewer runs isolated from the app origin anyway. Everything else is
     * a download.
     */
    private function crmFileResponse(ClientPlaceLogAttachment|ClientDocument $file): BinaryFileResponse
    {
        abort_unless($file->fileExists(), 404);

        $inline = $file->opensInline();

        $response = response()->file($file->absolutePath(), [
            'X-Content-Type-Options' => 'nosniff',
            ...($inline && $file->isPdf() ? [] : [
                'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'",
            ]),
        ]);

        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->downloadName(),
            'attachment.'.($file->extension ?: 'bin'),
        ));

        return $response;
    }
}
