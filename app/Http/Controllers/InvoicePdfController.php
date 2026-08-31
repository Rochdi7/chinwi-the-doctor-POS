<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Support\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        ActivityLog::record(
            'invoice.printed',
            $invoice,
            __('app.pdf.titre').' '.$invoice->numero,
            (float) $invoice->total_ttc,
        );

        [$body, $filename] = InvoicePdf::render($invoice);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
