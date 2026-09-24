<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Presenters\Crm\ClientPresenter;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every signed-in user can browse clients; only content managers edit them.
 */
class ClientController extends Controller
{
    public function index(Request $request, ClientPresenter $presenter): Response
    {
        $search = trim((string) $request->query('search', ''));

        $clients = Client::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->withCount(['places' => fn ($query) => $query->active()])
            ->withMax('places', 'last_logged_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('clients/index', [
            'clients' => $clients->map($presenter->listItem(...))->values()->all(),
            'filters' => ['search' => $search],
            'canManage' => $request->user()?->canManageContent() === true,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('clients/form', [
            'client' => null,
            'usedColors' => $this->usedColors(),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        $this->auditSuccess('clients.created', 'A CRM client was created.', $client);

        $this->flashToast('顧客を追加しました。', resource: [
            'type' => 'client',
            'id' => $client->id,
            'action' => 'created',
            'label' => $client->name,
        ]);

        return to_route('crm.clients.show', $client);
    }

    public function show(Request $request, Client $client, ClientPresenter $presenter): Response
    {
        $client->load([
            'contacts' => fn ($query) => $query->orderBy('name'),
            'places' => fn ($query) => $query->orderByRaw('archived_at is not null')->orderBy('kind')->orderBy('name'),
        ]);

        $viewer = $request->user();

        abort_unless($viewer instanceof User, 403);

        $documents = $client->documents()->with(['place', 'uploader'])->newestFirst()->get();

        return Inertia::render('clients/show', [
            'client' => $presenter->detail($client),
            'documents' => $documents->map(fn (ClientDocument $document): array => $presenter->document($document, $viewer))->values()->all(),
            'documentLimits' => $presenter->documentLimits(),
            'canManage' => $viewer->canManageContent(),
        ]);
    }

    public function edit(Client $client, ClientPresenter $presenter): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('clients/form', [
            'client' => [...$presenter->summary($client), 'note' => $client->note],
            'usedColors' => $this->usedColors($client),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        $this->auditSuccess('clients.updated', 'A CRM client was updated.', $client, [
            'changed' => array_values(array_diff(array_keys($client->getChanges()), ['updated_at'])),
        ]);

        $this->flashToast('顧客を更新しました。', resource: [
            'type' => 'client',
            'id' => $client->id,
            'action' => 'updated',
            'label' => $client->name,
        ]);

        return to_route('crm.clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('clients.deleted', 'A CRM client was deleted.', $client);

        $client->delete();

        $this->flashToast('顧客を削除しました。');

        return to_route('crm.clients.index');
    }

    /**
     * Colors other clients already use, so the form can suggest one that
     * stands apart on the map.
     *
     * @return list<string>
     */
    private function usedColors(?Client $except = null): array
    {
        return Client::query()
            ->when($except instanceof Client, fn ($query) => $query->whereKeyNot($except?->id))
            ->pluck('color')
            ->values()
            ->all();
    }
}
