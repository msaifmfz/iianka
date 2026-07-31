<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Stock\StockPurchaseRecorder;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockPurchaseCorrectionRequest;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;

class StockPurchaseCorrectionController extends Controller
{
    public function store(
        StoreStockPurchaseCorrectionRequest $request,
        Stock $stock,
        StockPurchaseRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();
        $term = StockTerm::fromMonth($validated['term_starts_on']);

        $recorder->correct($stock->id, $term, $validated['quantity_to_subtract'], $request->user());

        $this->auditSuccess('admin.stocks.purchase_corrected', 'An admin corrected a stock purchase quantity.', $stock, [
            'term_starts_on' => $term->startsOn()->toDateString(),
            'quantity_to_subtract' => $validated['quantity_to_subtract'],
        ]);

        $this->flashToast('仕入数を訂正しました。', resource: [
            'type' => 'stock_purchase_cell',
            'id' => "{$stock->id}-{$term->startsOn()->toDateString()}",
            'action' => 'saved',
            'label' => "{$stock->name} / {$term->label()}",
        ]);

        return back();
    }
}
