<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\PlaceLogRecorder;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ClientPlaceLogAttachmentController extends Controller
{
    /**
     * Any signed-in user can see CRM history, so any can view its files.
     */
    public function show(ClientPlaceLogAttachment $clientPlaceLogAttachment): BinaryFileResponse
    {
        abort_unless($clientPlaceLogAttachment->fileExists(), 404);

        // Files are served inline from the app origin. Validation only admits
        // raster images and audio, and the sandbox policy stops any script
        // from running even if something slipped past it.
        $response = response()->file($clientPlaceLogAttachment->absolutePath(), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'",
        ]);

        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $clientPlaceLogAttachment->downloadName(),
            'attachment.'.($clientPlaceLogAttachment->extension ?: 'bin'),
        ));

        return $response;
    }

    public function destroy(Request $request, ClientPlaceLogAttachment $clientPlaceLogAttachment, PlaceLogRecorder $recorder): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $clientPlaceLogAttachment->log->isEditableBy($user), 403);

        $this->auditSuccess('client_place_log_attachments.deleted', 'A CRM history attachment was deleted.', $clientPlaceLogAttachment, [
            'client_place_log_id' => $clientPlaceLogAttachment->client_place_log_id,
        ]);

        $recorder->deleteAttachment($clientPlaceLogAttachment);

        $this->flashToast('添付を削除しました。');

        return back();
    }
}
