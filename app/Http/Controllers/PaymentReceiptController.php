<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Support\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        ActivityLog::record(
            'payment.printed',
            $payment,
            __('app.receipt.titre').' #'.$payment->id,
            (float) $payment->montant,
        );

        [$body, $filename] = InvoicePdf::receipt($payment);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
