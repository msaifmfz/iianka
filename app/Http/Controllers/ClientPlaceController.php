<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\PlaceLogRecorder;
use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Http\Presenters\Crm\ClientPresenter;
use App\Http\Requests\SaveClientPlaceRequest;
use App\Models\Client;
use App\Models\ClientPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Places are the pins on the CRM map. Content managers add and move them;
 * every user can log history against them.
 */
class ClientPlaceController extends Controller
{
    /**
     * `lat`/`lng` in the query pre-place the pin, e.g. after a long-press on
     * the map.
     */
    public function create(Request $request, Client $client, ClientPresenter $presenter): Response
    {
        Gate::authorize('manage-content');

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        return Inertia::render('clients/place-form', [
            'client' => $presenter->summary($client),
            'place' => null,
            'initialPosition' => is_numeric($lat) && is_numeric($lng)
                && abs((float) $lat) <= 90 && abs((float) $lng) <= 180
                ? ['lat' => (float) $lat, 'lng' => (float) $lng]
                : null,
            'otherPlaces' => $this->otherPlaces($client, null, $presenter),
            'kinds' => ClientPlaceKind::options(),
        ]);
    }

    public function store(SaveClientPlaceRequest $request, Client $client): RedirectResponse
    {
        $place = $client->places()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        $this->auditSuccess('client_places.created', 'A CRM client place was created.', $place, [
            'client_id' => $client->id,
        ]);

        $this->flashToast('地点を追加しました。');

        return to_route('crm.map', ['place' => $place->id]);
    }

    public function edit(ClientPlace $clientPlace, ClientPresenter $presenter): Response
    {
        Gate::authorize('manage-content');

        $client = $clientPlace->client()->firstOrFail();

        return Inertia::render('clients/place-form', [
            'client' => $presenter->summary($client),
            'place' => $presenter->place($clientPlace),
            'initialPosition' => null,
            'otherPlaces' => $this->otherPlaces($client, $clientPlace, $presenter),
            'kinds' => ClientPlaceKind::options(),
        ]);
    }

    public function update(SaveClientPlaceRequest $request, ClientPlace $clientPlace): RedirectResponse
    {
        $clientPlace->update($request->validated());

        $this->auditSuccess('client_places.updated', 'A CRM client place was updated.', $clientPlace, [
            'changed' => array_values(array_diff(array_keys($clientPlace->getChanges()), ['updated_at'])),
        ]);

        $this->flashToast('地点を更新しました。');

        return to_route('crm.map', ['place' => $clientPlace->id]);
    }

    public function destroy(ClientPlace $clientPlace, PlaceLogRecorder $recorder): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('client_places.deleted', 'A CRM client place was deleted.', $clientPlace, [
            'client_id' => $clientPlace->client_id,
            'logs_count' => $clientPlace->logs()->count(),
        ]);

        $clientId = $clientPlace->client_id;
        $recorder->deletePlace($clientPlace);

        $this->flashToast('地点を削除しました。');

        return to_route('crm.clients.show', $clientId);
    }

    /**
     * The client's other active pins, drawn faintly on the picker so a new
     * place is not dropped on top of an existing one.
     *
     * @return list<array<string, mixed>>
     */
    private function otherPlaces(Client $client, ?ClientPlace $except, ClientPresenter $presenter): array
    {
        return $client->places()
            ->active()
            ->when($except instanceof ClientPlace, fn ($query) => $query->whereKeyNot($except?->id))
            ->get()
            ->map($presenter->place(...))
            ->values()
            ->all();
    }
}
