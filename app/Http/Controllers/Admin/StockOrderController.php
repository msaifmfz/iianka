<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStockOrderRequest;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StockOrderController extends Controller
{
    public function __invoke(UpdateStockOrderRequest $request): RedirectResponse
    {
        $orderedIds = $request->orderedIds();
        $cases = 'CASE id';

        foreach ($orderedIds as $index => $stockId) {
            $cases .= sprintf(' WHEN %d THEN %d', $stockId, ($index + 1) * Stock::SORT_ORDER_STEP);
        }

        $cases .= ' END';

        Stock::query()
            ->whereKey($orderedIds)
            ->update(['sort_order' => DB::raw($cases)]);

        $this->auditSuccess(
            'admin.stocks.reordered',
            'The stock display order was updated.',
            metadata: ['ordered_ids' => $orderedIds],
        );

        $this->flashToast('在庫の表示順を保存しました。', resource: [
            'type' => 'stock',
            'id' => 'order',
            'action' => 'saved',
            'label' => '表示順',
        ]);

        return back();
    }
}
