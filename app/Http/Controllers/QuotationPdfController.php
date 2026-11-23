<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Quotation\QuotationPdf;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuotationPdfController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation, string $document, QuotationPdf $pdf): Response
    {
        Gate::authorize('view-all-content');

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdf->render($quotation, $document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$quotation->number.'-'.$document.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
