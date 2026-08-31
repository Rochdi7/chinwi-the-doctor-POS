<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(): Invoice
    {
        $client = Client::create(['raison_sociale' => 'CLIENT PDF']);
        $article = Article::create([
            'reference' => 'PDF-1',
            'designation' => 'hp laptop',
            'prix_vente' => 2500,
            'stock' => 10,
            'tva' => 20,
        ]);

        $invoice = Invoice::create([
            'numero' => Invoice::nextNumero(),
            'client_id' => $client->id,
            'date_facture' => now(),
            'statut' => 'validee',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'article_id' => $article->id,
            'designation' => 'hp laptop',
            'quantite' => 1,
            'prix_unitaire' => 2500,
            'remise' => 100,
            'tva' => 20,
        ]);

        return $invoice->refresh();
    }

    /**
     * The printed invoice follows the language the user is working in,
     * rather than always printing both.
     */
    #[DataProvider('locales')]
    public function test_invoice_prints_in_the_active_language(
        string $locale,
        string $expected,
        string $absent,
    ): void {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $invoice = $this->makeInvoice();
        session(['locale' => $locale]);

        $response = $this->get(route('invoice.pdf', $invoice));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        app()->setLocale($locale);
        $this->assertSame($expected, __('app.pdf.titre'));
        $this->assertNotSame($absent, __('app.pdf.titre'));
    }

    public static function locales(): array
    {
        return [
            'french' => ['fr', 'VENTE', 'مبيعة'],
            'arabic' => ['ar', 'مبيعة', 'VENTE'],
            // Darija says "بيعة", not the Fusha "مبيعة".
            'darija' => ['ary', 'بيعة', 'مبيعة'],
        ];
    }

    public function test_arabic_invoice_letters_are_joined_not_isolated(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin3@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $invoice = $this->makeInvoice();

        app()->setLocale('ar');
        [$pdf] = \App\Support\InvoicePdf::render($invoice);

        $this->assertStringStartsWith('%PDF-', $pdf);

        // Tajawal only: mPDF must not fall back to its bundled XBRiyaz.
        $this->assertMatchesRegularExpression('/Tajawal-Regular/', $pdf);
        $this->assertDoesNotMatchRegularExpression('/XBRiyaz/', $pdf);

        $text = (new \Smalot\PdfParser\Parser())
            ->parseContent($pdf)
            ->getText();

        // A shaped run lands in the Arabic Presentation Forms block. dompdf
        // emitted bare U+06xx letters instead, which is why فاتورة printed
        // as ةروتاف.
        $shaped = 0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp >= 0xFE70 && $cp <= 0xFEFF) {
                $shaped++;
            }
        }

        $this->assertGreaterThan(50, $shaped, 'Arabic text was shaped into joined forms');
    }

    public function test_discount_is_visible_in_the_totals(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin2@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $invoice = $this->makeInvoice();

        $html = view('pdf.invoice', [
            'invoice' => $invoice->load(['client', 'items']),
            'societe' => ['nom' => 'MA SOCIETE', 'adresse' => '', 'telephone' => '', 'email' => '', 'ice' => '', 'rc' => ''],
            'devise' => 'DH',
        ])->render();

        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        // 2 500 − 100 = 2 400 HT, ×1.2 = 2 880 TTC. Every step must be printed.
        $this->assertStringContainsString('2 500,00', $text, 'sub-total before discount');
        $this->assertStringContainsString('−100,00', $text, 'discount shown as a deduction');
        $this->assertStringContainsString('2 400,00', $text, 'total HT after discount');
        $this->assertStringContainsString('2 880,00', $text, 'total TTC');
    }
}
