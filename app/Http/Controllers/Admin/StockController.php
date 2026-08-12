<?php

namespace App\Http\Controllers\Admin;

use App\Application\Stock\Queries\StockTermReportService;
use App\Application\Stock\StockPurchaseRecorder;
use App\Domain\Stock\Parsing\StockNameNormalizer;
use App\Domain\Stock\ValueObjects\StockQuantity;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Stock\UsageSchedulePreviews;
use App\Http\Requests\Admin\StoreStockRequest;
use App\Http\Requests\Admin\UpdateStockRequest;
use App\Models\ScheduleStockBalance;
use App\Models\ScheduleStockMention;
use App\Models\Stock;
use App\Models\StockAlias;
use App\Services\BusinessDate;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(
        Request $request,
        StockTermReportService $reportService,
        UsageSchedulePreviews $usageSchedulePreviews,
    ): Response {
        Gate::authorize('manage-stocks');

        $term = $this->resolveTerm($request->query('month'));
        $isCurrent = $term->startsOn()->equalTo(StockTerm::current()->startsOn());

        return Inertia::render('admin/stocks/index', [
            'filters' => [
                'month' => $term->monthParam(),
                'previous_month' => $term->previous()->monthParam(),
                'next_month' => $term->next()->monthParam(),
                'is_current' => $isCurrent,
            ],
            // Closures, so the partial reload that fetches the previews below
            // does not rebuild the report and throw it away.
            'terms' => fn (): array => $reportService->build($term),
            // Requested by the page the first time a usage bucket is opened;
            // most visits never look at one.
            'usageSchedulePreviews' => Inertia::optional(fn (): array => $usageSchedulePreviews->forTerm($term)),
            'stockOrder' => fn (): EloquentCollection => Stock::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'is_active']),
            'today' => BusinessDate::today()->toDateString(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-stocks');

        return Inertia::render('admin/stocks/form', [
            'managedStock' => null,
        ]);
    }

    public function store(StoreStockRequest $request, StockPurchaseRecorder $recorder): RedirectResponse
    {
        $validated = $request->validated();
        $normalizer = new StockNameNormalizer;

        $stock = DB::transaction(function () use ($validated, $normalizer, $recorder, $request): Stock {
            $lastSortOrder = Stock::query()
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('sort_order');

            $stock = Stock::query()->create([
                'sku' => ($validated['sku'] ?? '') !== '' ? $validated['sku'] : null,
                'name' => $validated['name'],
                'normalized_name' => $normalizer->normalize($validated['name']),
                'allows_fractional_quantity' => $validated['allows_fractional_quantity'],
                'is_active' => true,
                'sort_order' => ((int) $lastSortOrder) + Stock::SORT_ORDER_STEP,
            ]);

            foreach ($validated['aliases'] as $alias) {
                $stock->aliases()->create([
                    'alias' => $alias,
                    'normalized_alias' => $normalizer->normalize($alias),
                    'is_active' => true,
                ]);
            }

            $initialQuantity = $validated['initial_quantity'] ?? null;

            if (is_string($initialQuantity)
                && StockQuantity::fromDecimal($initialQuantity)->isPositive()) {
                $recorder->add($stock->id, StockTerm::current(), $initialQuantity, $request->user(), '初期在庫');
            }

            return $stock;
        });

        $this->auditSuccess('admin.stocks.created', 'A stock item was created.', $stock, [
            'name' => $stock->name,
            'aliases' => $validated['aliases'],
            'initial_quantity' => $validated['initial_quantity'] ?? null,
        ]);

        $this->flashToast('在庫を追加しました。', resource: [
            'type' => 'stock',
            'id' => $stock->id,
            'action' => 'created',
            'label' => $stock->name,
        ]);

        return redirect()
            ->route('admin.stocks.index');
    }

    public function edit(Stock $stock): Response
    {
        Gate::authorize('manage-stocks');

        return Inertia::render('admin/stocks/form', [
            'managedStock' => $this->stockPayload($stock),
        ]);
    }

    public function update(UpdateStockRequest $request, Stock $stock): RedirectResponse
    {
        $validated = $request->validated();
        $normalizer = new StockNameNormalizer;

        DB::transaction(function () use ($validated, $normalizer, $stock): void {
            $stock->update([
                'sku' => ($validated['sku'] ?? '') !== '' ? $validated['sku'] : null,
                'name' => $validated['name'],
                'normalized_name' => $normalizer->normalize($validated['name']),
                'allows_fractional_quantity' => $validated['allows_fractional_quantity'],
                'is_active' => $validated['is_active'],
            ]);

            $desired = [];

            foreach ($validated['aliases'] as $alias) {
                $desired[$normalizer->normalize($alias)] = $alias;
            }

            foreach ($stock->aliases as $existing) {
                if (! isset($desired[$existing->normalized_alias])) {
                    $existing->delete();

                    continue;
                }

                if ($existing->alias !== $desired[$existing->normalized_alias]) {
                    $existing->update(['alias' => $desired[$existing->normalized_alias]]);
                }

                unset($desired[$existing->normalized_alias]);
            }

            foreach ($desired as $normalizedAlias => $alias) {
                $stock->aliases()->create([
                    'alias' => $alias,
                    'normalized_alias' => $normalizedAlias,
                    'is_active' => true,
                ]);
            }
        });

        $this->auditSuccess('admin.stocks.updated', 'A stock item was updated.', $stock, [
            'changed' => array_values(array_diff(array_keys($stock->getChanges()), ['updated_at'])),
            'aliases' => $validated['aliases'],
        ]);

        $this->flashToast('在庫情報を修正しました。', resource: [
            'type' => 'stock',
            'id' => $stock->id,
            'action' => 'updated',
            'label' => $stock->name,
        ]);

        return redirect()
            ->route('admin.stocks.index');
    }

    public function destroy(Stock $stock): RedirectResponse
    {
        Gate::authorize('manage-stocks');
        abort_if($this->hasHistory($stock), 422, '履歴のある在庫は削除できません。代わりに無効化してください。');

        $this->auditSuccess('admin.stocks.deleted', 'A stock item was deleted.', $stock, [
            'name' => $stock->name,
        ]);

        $stock->delete();

        $this->flashToast('在庫を削除しました。');

        return redirect()
            ->route('admin.stocks.index');
    }

    private function resolveTerm(mixed $month): StockTerm
    {
        if (is_string($month) && $month !== '') {
            try {
                return StockTerm::fromMonth($month);
            } catch (Exception) {
                // Fall through to the current term.
            }
        }

        return StockTerm::current();
    }

    /**
     * @return array<string, mixed>
     */
    private function stockPayload(Stock $stock): array
    {
        return [
            'id' => $stock->id,
            'sku' => $stock->sku,
            'name' => $stock->name,
            'current_quantity' => $stock->current_quantity,
            'allows_fractional_quantity' => $stock->allows_fractional_quantity,
            'is_active' => $stock->is_active,
            'aliases' => $stock->aliases
                ->toBase()
                ->map(fn (StockAlias $alias): array => [
                    'id' => $alias->id,
                    'alias' => $alias->alias,
                ])
                ->values()
                ->all(),
            'has_history' => $this->hasHistory($stock),
        ];
    }

    private function hasHistory(Stock $stock): bool
    {
        if ($stock->transactions()->exists()) {
            return true;
        }
        if ($stock->purchases()->exists()) {
            return true;
        }
        if (ScheduleStockBalance::query()->where('stock_id', $stock->id)->exists()) {
            return true;
        }

        return ScheduleStockMention::query()->where('stock_id', $stock->id)->exists();
    }
}
