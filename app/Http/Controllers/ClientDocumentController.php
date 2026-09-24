<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\ClientDocumentStore;
use App\Http\Controllers\Concerns\ServesCrmFiles;
use App\Http\Requests\StoreClientDocumentRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documents filed on a client. Every user views and adds them; uploaders
 * (or admins) delete their own.
 */
class ClientDocumentController extends Controller
{
    use ServesCrmFiles;

    public function store(StoreClientDocumentRequest $request, Client $client, ClientDocumentStore $documents): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $document = $documents->create($client, $user, $request->uploadedFile(), $request->documentFields());

        $this->auditSuccess('client_documents.created', 'A CRM client document was added.', $document, [
            'client_id' => $client->id,
        ]);

        $this->flashToast('書類を登録しました。');

        return back();
    }

    public function show(ClientDocument $clientDocument): BinaryFileResponse
    {
        return $this->crmFileResponse($clientDocument);
    }

    public function destroy(Request $request, ClientDocument $clientDocument, ClientDocumentStore $documents): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $clientDocument->isDeletableBy($user), 403);

        $this->auditSuccess('client_documents.deleted', 'A CRM client document was deleted.', $clientDocument, [
            'client_id' => $clientDocument->client_id,
        ]);

        $documents->delete($clientDocument);

        $this->flashToast('書類を削除しました。');

        return back();
    }
}
