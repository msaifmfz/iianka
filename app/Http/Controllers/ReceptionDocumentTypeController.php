<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceptionDocumentTypeRequest;
use App\Http\Requests\UpdateReceptionDocumentTypeRequest;
use App\Models\ReceptionDocumentType;
use App\Services\ReceptionCasePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionDocumentTypeController extends Controller
{
    public function index(Request $request, ReceptionCasePresenter $presenter): Response
    {
        $this->authorize('viewAny', ReceptionDocumentType::class);

        $documentTypes = ReceptionDocumentType::query()
            ->withCount('receptionCases')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('reception/document-types/index', [
            'documentTypes' => $presenter->documentTypes($documentTypes),
        ]);
    }

    public function store(StoreReceptionDocumentTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['sort_order'] ??= ((int) ReceptionDocumentType::query()->max('sort_order')) + ReceptionDocumentType::SORT_ORDER_STEP;

        $documentType = ReceptionDocumentType::query()->create($validated);

        $this->auditSuccess('reception_document_types.created', 'A reception document type was created.', $documentType);

        $this->flashToast('案件書類を追加しました。', resource: [
            'type' => 'reception_document_type',
            'id' => $documentType->id,
            'action' => 'created',
            'label' => $documentType->name,
        ]);

        return back();
    }

    public function update(UpdateReceptionDocumentTypeRequest $request, ReceptionDocumentType $receptionDocumentType): RedirectResponse
    {
        $wasActive = $receptionDocumentType->is_active;

        $receptionDocumentType->update($request->validated());

        $this->auditSuccess(
            $wasActive && ! $receptionDocumentType->is_active
                ? 'reception_document_types.deactivated'
                : 'reception_document_types.updated',
            'A reception document type was updated.',
            $receptionDocumentType,
            ['changed' => array_values(array_diff(array_keys($receptionDocumentType->getChanges()), ['updated_at']))],
        );

        $this->flashToast('案件書類を更新しました。', resource: [
            'type' => 'reception_document_type',
            'id' => $receptionDocumentType->id,
            'action' => 'updated',
            'label' => $receptionDocumentType->name,
        ]);

        return back();
    }
}
