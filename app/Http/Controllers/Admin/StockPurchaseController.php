<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Stock\StockPurchaseRecorder;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockPurchaseRequest;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;

class StockPurchaseController extends Controller
{
    public function store(
        StoreStockPurchaseRequest $request,
        Stock $stock,
        StockPurchaseRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();
        $term = StockTerm::fromMonth($validated['term_starts_on']);

        $recorder->add($stock->id, $term, $validated['quantity_to_add'], $request->user());

        $this->auditSuccess('admin.stocks.purchase_added', 'A stock purchase quantity was added.', $stock, [
            'term_starts_on' => $term->startsOn()->toDateString(),
            'quantity_to_add' => $validated['quantity_to_add'],
        ]);

        $this->flashToast('仕入数を追加しました。', resource: [
            'type' => 'stock_purchase_cell',
            'id' => "{$stock->id}-{$term->startsOn()->toDateString()}",
            'action' => 'saved',
            'label' => "{$stock->name} / {$term->label()}",
        ]);

        return back();
    }
}
