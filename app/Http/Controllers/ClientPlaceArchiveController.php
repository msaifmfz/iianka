<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClientPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Archiving hides a finished place from the map by default while keeping its
 * history; the map's "アーカイブも表示" toggle brings it back into view.
 */
class ClientPlaceArchiveController extends Controller
{
    public function store(ClientPlace $clientPlace): RedirectResponse
    {
        Gate::authorize('manage-content');

        $clientPlace->update(['archived_at' => now()]);

        $this->auditSuccess('client_places.archived', 'A CRM client place was archived.', $clientPlace);

        $this->flashToast('地点をアーカイブしました。');

        return back();
    }

    public function destroy(ClientPlace $clientPlace): RedirectResponse
    {
        Gate::authorize('manage-content');

        $clientPlace->update(['archived_at' => null]);

        $this->auditSuccess('client_places.unarchived', 'A CRM client place was restored from the archive.', $clientPlace);

        $this->flashToast('地点のアーカイブを解除しました。');

        return back();
    }
}
