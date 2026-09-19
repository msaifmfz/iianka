<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Crm\Enums\ClientPlaceLogType;
use App\Domain\Crm\Enums\ClientReaction;
use App\Http\Presenters\Crm\ClientPresenter;
use App\Models\Client;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The CRM map: every client's places as pins, colored by client.
 *
 * Pins load in one light payload. A tapped pin's history is the
 * `selectedPlace` prop, fetched with a partial reload carrying `?place=`, so
 * the URL deep-links to an open panel and reloading keeps it open.
 */
class CrmMapController extends Controller
{
    /**
     * How many history entries the panel carries by default. The client-wide
     * timeline is unbounded in principle, so the newest page loads with the
     * pin and `?logs=all` fetches the rest on demand.
     */
    public const int RECENT_LOG_LIMIT = 100;

    public function __invoke(Request $request, ClientPresenter $presenter): Response
    {
        $showArchived = $request->boolean('archived');
        $viewer = $request->user();

        abort_unless($viewer instanceof User, 403);

        $selectedPlaceId = $request->integer('place');
        $showAllLogs = $request->string('logs')->value() === 'all';

        return Inertia::render('crm-map/index', [
            'clients' => fn (): array => array_map(
                $presenter->summary(...),
                Client::query()->orderBy('name')->get()->all(),
            ),
            'places' => fn (): array => array_map(
                $presenter->pin(...),
                ClientPlace::query()
                    ->whereHas('client')
                    ->when(! $showArchived, fn ($query) => $query->active())
                    ->get()
                    ->all(),
            ),
            'filters' => ['archived' => $showArchived],
            'selectedPlace' => fn (): ?array => $selectedPlaceId > 0
                ? $this->placeDetail($selectedPlaceId, $viewer, $presenter, $showAllLogs)
                : null,
            'canManage' => $viewer->canManageContent(),
            'logTypes' => ClientPlaceLogType::options(),
            'reactions' => ClientReaction::options(),
            'attachmentLimits' => $presenter->attachmentLimits(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function placeDetail(int $placeId, User $viewer, ClientPresenter $presenter, bool $showAllLogs): ?array
    {
        $place = ClientPlace::query()
            ->whereHas('client')
            ->with([
                'client.contacts' => fn ($query) => $query->orderBy('name'),
            ])
            ->find($placeId);

        if (! $place instanceof ClientPlace) {
            return null;
        }

        // An IN over the indexed `client_place_id` rather than a correlated
        // EXISTS: the timeline spans every place of the client, and this
        // query runs again on each pin tap.
        $logs = ClientPlaceLog::query()
            ->whereIn('client_place_id', ClientPlace::query()
                ->where('client_id', $place->client_id)
                ->select('id'));

        $total = $logs->clone()->count();

        $logs = $logs
            ->with(['place', 'user', 'contact', 'attachments'])
            ->latest('occurred_at')
            ->latest('id')
            ->when(! $showAllLogs, fn ($query) => $query->limit(self::RECENT_LOG_LIMIT))
            ->get();

        return $presenter->placeDetail($place, $viewer, $logs, $total);
    }
}
