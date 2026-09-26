<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Support\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The thermal receipt of a whole sale, served as a file download. The A4
 * facture is InvoicePdfController and opens inline.
 */
class InvoiceReceiptController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        ActivityLog::record(
            'invoice.receipt',
            $invoice,
            __('app.receipt.titre_vente').' '.$invoice->numero,
            (float) $invoice->total_ttc,
        );

        [$body, $filename] = InvoicePdf::saleReceipt($invoice);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
