<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConstructionSubcontractorRequest;
use App\Models\ConstructionSubcontractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConstructionSubcontractorController extends Controller
{
    public function update(UpdateConstructionSubcontractorRequest $request, ConstructionSubcontractor $constructionSubcontractor): RedirectResponse
    {
        $constructionSubcontractor->update($request->validated());

        $this->auditSuccess('construction_subcontractors.updated', 'A construction subcontractor was updated.', $constructionSubcontractor);

        $this->flashToast('下請けを修正しました。', resource: [
            'type' => 'construction_subcontractor',
            'id' => $constructionSubcontractor->id,
            'action' => 'updated',
            'label' => $constructionSubcontractor->name,
        ]);

        return back();
    }

    public function destroy(Request $request, ConstructionSubcontractor $constructionSubcontractor): RedirectResponse
    {
        abort_unless($request->user()?->canManageContent() === true, 403);

        $this->auditSuccess('construction_subcontractors.deleted', 'A construction subcontractor was deleted.', $constructionSubcontractor);

        $constructionSubcontractor->delete();

        $this->flashToast('下請けを削除しました。');

        return back();
    }
}
