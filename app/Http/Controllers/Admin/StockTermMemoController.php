<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Stock\StockTermMemoUpdater;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStockTermMemoRequest;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;

class StockTermMemoController extends Controller
{
    public function update(
        UpdateStockTermMemoRequest $request,
        Stock $stock,
        StockTermMemoUpdater $updater,
    ): RedirectResponse {
        $validated = $request->validated();
        $term = StockTerm::fromMonth($validated['term_starts_on']);
        $memo = $validated['memo'] ?? null;

        $updater->update($stock->id, $term, $memo);

        $this->auditSuccess('admin.stocks.term_memo_updated', 'An admin updated a stock term memo.', $stock, [
            'term_starts_on' => $term->startsOn()->toDateString(),
            'action' => $memo === null ? 'cleared' : 'saved',
        ]);

        $this->flashToast('メモを保存しました。', resource: [
            'type' => 'stock_term_memo',
            'id' => "{$stock->id}-{$term->startsOn()->toDateString()}",
            'action' => 'saved',
            'label' => "{$stock->name} / {$term->label()}",
        ]);

        return back();
    }
}
