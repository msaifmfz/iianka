<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\PlaceLogRecorder;
use App\Http\Controllers\Concerns\ServesCrmFiles;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientPlaceLogAttachmentController extends Controller
{
    use ServesCrmFiles;

    /**
     * Any signed-in user can see CRM history, so any can view its files.
     */
    public function show(ClientPlaceLogAttachment $clientPlaceLogAttachment): BinaryFileResponse
    {
        return $this->crmFileResponse($clientPlaceLogAttachment);
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
