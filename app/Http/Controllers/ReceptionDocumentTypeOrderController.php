<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReceptionDocumentTypeOrderRequest;
use App\Models\ReceptionDocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReceptionDocumentTypeOrderController extends Controller
{
    public function __invoke(UpdateReceptionDocumentTypeOrderRequest $request): RedirectResponse
    {
        $orderedIds = $request->orderedIds();

        // Renormalize every position in a single UPDATE. The ids are integers
        // (validated + cast in orderedIds()), so inlining them into the CASE
        // expression is injection-safe and avoids one query per row.
        $cases = 'CASE id';

        foreach ($orderedIds as $index => $documentTypeId) {
            $cases .= sprintf(' WHEN %d THEN %d', $documentTypeId, ($index + 1) * ReceptionDocumentType::SORT_ORDER_STEP);
        }

        $cases .= ' END';

        ReceptionDocumentType::query()
            ->whereKey($orderedIds)
            ->update(['sort_order' => DB::raw($cases)]);

        $this->auditSuccess(
            'reception_document_types.reordered',
            'Reception document type order was updated.',
            metadata: ['ordered_ids' => $orderedIds],
        );

        $this->flashToast('案件書類の表示順を保存しました。', resource: [
            'type' => 'reception_document_type',
            'id' => 'order',
            'action' => 'saved',
            'label' => '表示順',
        ]);

        return back();
    }
}
