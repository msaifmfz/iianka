<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\PlaceLogRecorder;
use App\Http\Requests\SaveClientPlaceLogRequest;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * History entries on a CRM place. Every user logs; authors (or admins)
 * edit and delete their own.
 */
class ClientPlaceLogController extends Controller
{
    public function store(SaveClientPlaceLogRequest $request, ClientPlace $clientPlace, PlaceLogRecorder $recorder): RedirectResponse
    {
        $log = $recorder->create($clientPlace, $this->user($request), $request->logFields(), $request->attachmentUploads());

        $this->auditSuccess('client_place_logs.created', 'A CRM history entry was created.', $log, [
            'client_place_id' => $clientPlace->id,
            'attachments_count' => $log->attachments()->count(),
        ]);

        $this->flashToast('記録を追加しました。');

        return back();
    }

    public function update(SaveClientPlaceLogRequest $request, ClientPlaceLog $clientPlaceLog, PlaceLogRecorder $recorder): RedirectResponse
    {
        $recorder->update($clientPlaceLog, $this->user($request), $request->logFields(), $request->attachmentUploads());

        $this->auditSuccess('client_place_logs.updated', 'A CRM history entry was updated.', $clientPlaceLog);

        $this->flashToast('記録を更新しました。');

        return back();
    }

    public function destroy(Request $request, ClientPlaceLog $clientPlaceLog, PlaceLogRecorder $recorder): RedirectResponse
    {
        abort_unless($clientPlaceLog->isEditableBy($this->user($request)), 403);

        $this->auditSuccess('client_place_logs.deleted', 'A CRM history entry was deleted.', $clientPlaceLog, [
            'client_place_id' => $clientPlaceLog->client_place_id,
        ]);

        $recorder->delete($clientPlaceLog);

        $this->flashToast('記録を削除しました。');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
