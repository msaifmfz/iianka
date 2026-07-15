<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStockPurchaseRequest;
use App\Models\Stock;
use App\Services\Stock\StockPurchaseRecorder;
use App\Services\Stock\StockTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StockPurchaseController extends Controller
{
    public function update(UpdateStockPurchaseRequest $request, Stock $stock, StockPurchaseRecorder $recorder): RedirectResponse
    {
        $validated = $request->validated();
        $term = StockTerm::fromMonth($validated['term_starts_on']);

        DB::transaction(function () use ($validated, $term, $stock, $recorder, $request): void {
            $recorder->record($stock->id, $term, $validated['quantity'], $request->user());
        });

        $this->auditSuccess('admin.stocks.purchase_updated', 'An admin updated a stock purchase quantity.', $stock, [
            'term_starts_on' => $term->startsOn()->toDateString(),
            'quantity' => $validated['quantity'],
        ]);

        $this->flashToast('仕入数を更新しました。', resource: [
            'type' => 'stock_purchase_cell',
            'id' => "{$stock->id}-{$term->startsOn()->toDateString()}",
            'action' => 'saved',
            'label' => "{$stock->name} / {$term->label()}",
        ]);

        return back();
    }
}
