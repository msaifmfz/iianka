<?php

namespace App\Http\Controllers;

use App\Models\ConstructionSubcontractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConstructionSubcontractorController extends Controller
{
    public function destroy(Request $request, ConstructionSubcontractor $constructionSubcontractor): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $this->auditSuccess('construction_subcontractors.deleted', 'A construction subcontractor was deleted.', $constructionSubcontractor);

        $constructionSubcontractor->delete();

        return back()->with('status', '下請けを削除しました。');
    }
}
