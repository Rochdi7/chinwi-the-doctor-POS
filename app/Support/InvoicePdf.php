<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Mpdf\Mpdf;

class InvoicePdf
{
    /**
     * Arabic goes through mPDF, French through dompdf.
     *
     * dompdf has no text-shaping or bidi engine: it draws each Arabic
     * codepoint in its isolated form, left to right, so فاتورة comes out as
     * ةروتاف. mPDF ships both an Arabic joiner and a bidi algorithm, so the
     * Arabic documents are rendered there with Tajawal.
     */
    public static function render(Invoice $invoice): array
    {
        $invoice->loadMissing(['client', 'items']);

        return self::build(
            frenchView: 'pdf.invoice',
            arabicView: 'pdf.invoice-ar',
            data: ['invoice' => $invoice] + self::common(),
            filename: 'facture-'.$invoice->numero.'.pdf',
        );
    }

    /**
     * Receipt for one payment: what the client handed over on this visit,
     * plus what is still owed on the invoice.
     */
    public static function receipt(Payment $payment): array
    {
        $payment->loadMissing(['client', 'invoice']);

        return self::build(
            frenchView: 'pdf.receipt',
            arabicView: 'pdf.receipt',
            data: ['payment' => $payment] + self::common(),
            filename: 'recu-'.$payment->id.'.pdf',
        );
    }

    private static function common(): array
    {
        return [
            'societe' => [
                'nom' => Setting::get('societe_nom', 'chinwi_the_doctor'),
                'adresse' => Setting::get('societe_adresse'),
                'telephone' => Setting::get('societe_telephone'),
                'email' => Setting::get('societe_email'),
                'ice' => Setting::get('societe_ice'),
                'rc' => Setting::get('societe_rc'),
            ],
            'devise' => Setting::get('devise', 'DH'),
            'logo' => self::logo(),
        ];
    }

    /**
     * Both PDF engines read the logo off disk, not over HTTP, so it is
     * inlined as a data URI: dompdf would otherwise need remote fetching
     * enabled, and mPDF resolves bare paths against its own working dir.
     * Returns null when the file is missing so the templates just skip it.
     */
    private static function logo(): ?string
    {
        $path = public_path('assets/chinwi-the-doctor.jpeg');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path));
    }

    private static function build(string $frenchView, string $arabicView, array $data, string $filename): array
    {
        $body = Locales::isArabicScript()
            ? self::arabic($arabicView, $data)
            : self::french($frenchView, $data);

        return [$body, $filename];
    }

    private static function french(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)->setPaper('a4')->output();
    }

    private static function arabic(string $view, array $data): string
    {
        $tmp = storage_path('app/mpdf');

        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            // Leave these off: they swap Arabic runs onto mPDF's bundled
            // XBRiyaz instead of Tajawal. Shaping does not depend on them.
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tmp,
            'fontDir' => array_merge(
                (new \Mpdf\Config\ConfigVariables)->getDefaults()['fontDir'],
                [resource_path('fonts')],
            ),
            'fontdata' => (new \Mpdf\Config\FontVariables)->getDefaults()['fontdata'] + [
                'tajawal' => [
                    'R' => 'Tajawal-Regular.ttf',
                    'B' => 'Tajawal-Bold.ttf',
                    'useOTL' => 0xFF,   // enable OpenType layout: Arabic joining
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'tajawal',
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', 'S');
    }
}
