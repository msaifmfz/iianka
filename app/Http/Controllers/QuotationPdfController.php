<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Quotation\QuotationPdf;
use App\Models\Quotation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuotationPdfController extends Controller
{
    public function __invoke(Quotation $quotation, string $document, QuotationPdf $pdf): Response
    {
        Gate::authorize('view-all-content');

        return response($pdf->render($quotation, $document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$quotation->number.'-'.$document.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
