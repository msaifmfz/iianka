<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Quotation\QuotationCatalog;
use App\Application\Quotation\QuotationPdf;
use App\Domain\Quotation\QuotationCalculator;
use App\Http\Requests\SaveQuotationRequest;
use App\Models\Quotation;
use App\Services\BusinessDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationCatalog $catalog, private readonly QuotationCalculator $calculator) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-all-content');
        $search = mb_substr($request->string('search')->trim()->toString(), 0, 120);

        return Inertia::render('quotations/index', [
            'quotations' => Quotation::query()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('client_name', 'like', '%'.$search.'%')->orWhere('project_name', 'like', '%'.$search.'%')->orWhere('number', 'like', '%'.$search.'%');
                }))
                ->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Quotation $quote): array => [
                    'id' => $quote->id, 'number' => $quote->number, 'client_name' => $quote->client_name,
                    'project_name' => $quote->project_name, 'issued_on' => $quote->issued_on->toDateString(),
                    'total' => $quote->calculation['total'], 'item_count' => count($quote->calculation['lines']),
                ]),
            'search' => $search,
            'canManage' => $request->user()?->canManageContent() === true,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage-content');

        return $this->form($request);
    }

    public function edit(Request $request, Quotation $quotation): Response
    {
        Gate::authorize('manage-content');

        return $this->form($request, $quotation);
    }

    private function form(Request $request, ?Quotation $quotation = null): Response
    {
        return Inertia::render('quotations/form', [
            'quotation' => $quotation,
            'catalog' => array_values(array_replace($this->catalog->indexed(), $quotation->catalog_snapshot ?? [])),
            'conditions' => QuotationCalculator::CONDITIONS,
            'categories' => QuotationCalculator::CATEGORIES,
            'today' => BusinessDate::today()->toDateString(),
            'defaultPerson' => $request->user()?->name,
        ]);
    }

    public function preview(SaveQuotationRequest $request, ?Quotation $quotation = null): JsonResponse
    {
        return response()->json($this->calculator->calculate($request->validated(), array_replace($this->catalog->indexed(), $quotation->catalog_snapshot ?? [])));
    }

    public function previewPdf(SaveQuotationRequest $request, QuotationPdf $pdf, string $document, ?Quotation $quotation = null): JsonResponse
    {
        $draft = new Quotation([
            ...$this->attributes($request->validated(), $quotation->catalog_snapshot ?? []),
            'number' => $quotation->number ?? 'PREVIEW',
            'issuer' => $quotation->issuer ?? config('quotation.issuer'),
        ]);

        return response()->json([
            'pdf' => base64_encode($pdf->render($draft, $document, preview: true)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveQuotationRequest $request): RedirectResponse
    {
        $quote = DB::transaction(function () use ($request): Quotation {
            $quote = Quotation::create([
                ...$this->attributes($request->validated()),
                'created_by' => $request->user()?->id,
                'issuer' => config('quotation.issuer'),
                'version' => 1,
            ]);
            $quote->update(['number' => 'Q-'.$quote->issued_on->format('Ymd').'-'.str_pad((string) $quote->id, 5, '0', STR_PAD_LEFT)]);

            return $quote;
        });
        $this->auditSuccess('quotations.created', 'A quotation was created.', $quote);
        $this->flashToast('見積書を保存しました。PDFをダウンロードできます。');

        return to_route('quotations.show', $quote);
    }

    public function update(SaveQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $attributes = $this->attributes($request->validated(), $quotation->catalog_snapshot);
        $changes = (new Quotation)->fill([...$attributes, 'version' => $request->integer('version') + 1]);
        $updated = Quotation::query()->whereKey($quotation->id)->where('version', $request->integer('version'))
            ->update($changes->getAttributes());
        if ($updated !== 1) {
            throw ValidationException::withMessages(['version' => '他の画面で更新されています。再読み込みして最新の内容を確認してください。']);
        }
        $this->auditSuccess('quotations.updated', 'A quotation was updated.', $quotation);
        $this->flashToast('見積書を更新しました。');

        return to_route('quotations.show', $quotation);
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        Gate::authorize('view-all-content');

        return Inertia::render('quotations/show', ['quotation' => $quotation, 'canManage' => $request->user()?->canManageContent() === true]);
    }

    /** @param array<string, mixed> $input
     * @param  array<string, array<string, mixed>>  $snapshot
     * @return array<string, mixed>
     */
    private function attributes(array $input, array $snapshot = []): array
    {
        unset($input['version']);
        $catalog = array_replace($this->catalog->indexed(), $snapshot);
        $used = array_filter(array_column($input['items'], 'catalog_id'));

        return [
            'client_name' => $input['client_name'], 'project_name' => $input['project_name'], 'issued_on' => $input['issued_on'],
            'input' => $input, 'calculation' => $this->calculator->calculate($input, $catalog),
            'catalog_snapshot' => array_intersect_key($catalog, array_flip($used)),
        ];
    }
}
