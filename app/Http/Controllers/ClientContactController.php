<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Presenters\Crm\ClientPresenter;
use App\Http\Requests\SaveClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ClientContactController extends Controller
{
    public function store(SaveClientContactRequest $request, Client $client, ClientPresenter $presenter): RedirectResponse|JsonResponse
    {
        $contact = $client->contacts()->create($request->validated());

        $this->auditSuccess('client_contacts.created', 'A CRM client contact was created.', $contact);

        if ($request->expectsJson()) {
            return response()->json(['contact' => $presenter->contact($contact)], 201);
        }

        $this->flashToast('担当者を追加しました。');

        return back();
    }

    public function update(SaveClientContactRequest $request, ClientContact $clientContact): RedirectResponse
    {
        $clientContact->update($request->validated());

        $this->auditSuccess('client_contacts.updated', 'A CRM client contact was updated.', $clientContact);

        $this->flashToast('担当者を更新しました。');

        return back();
    }

    public function destroy(ClientContact $clientContact): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('client_contacts.deleted', 'A CRM client contact was deleted.', $clientContact);

        $clientContact->delete();

        $this->flashToast('担当者を削除しました。');

        return back();
    }
}
