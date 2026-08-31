<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\InvoicePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientOptionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));
    }

    private function walkInInvoice(): Invoice
    {
        $article = Article::create([
            'reference' => 'ART-0001',
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 20,
            'tva' => 0,
        ]);

        $invoice = Invoice::create([
            'numero' => Invoice::nextNumero(),
            'client_id' => null,
            'date_facture' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'article_id' => $article->id,
            'designation' => 'Lait 1L',
            'quantite' => 2,
            'prix_unitaire' => 10,
            'tva' => 0,
        ]);

        return $invoice->refresh();
    }

    public function test_an_invoice_can_be_saved_with_no_client(): void
    {
        $invoice = $this->walkInInvoice();

        $this->assertNull($invoice->client_id);
        $this->assertEquals(20.00, $invoice->total_ttc);
        $this->assertSame('validee', $invoice->statut);
    }

    public function test_paying_a_clientless_invoice_still_works_end_to_end(): void
    {
        $invoice = $this->walkInInvoice();

        Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => null,
            'date_paiement' => now(),
            'montant' => 20,
            'mode' => 'especes',
        ]);

        $this->assertSame('payee', $invoice->refresh()->statut);
        $this->assertEquals(0.0, $invoice->reste());
        $this->assertEquals(20.00, Caisse::solde(), 'cash still reaches the drawer');
    }

    /** The three PDFs used to read $invoice->client->raison_sociale directly. */
    #[DataProvider('locales')]
    public function test_the_invoice_prints_without_a_client(string $locale): void
    {
        $invoice = $this->walkInInvoice();

        app()->setLocale($locale);
        [$pdf] = InvoicePdf::render($invoice);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    #[DataProvider('locales')]
    public function test_the_receipt_prints_without_a_client(string $locale): void
    {
        $invoice = $this->walkInInvoice();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => null,
            'date_paiement' => now(),
            'montant' => 20,
            'mode' => 'especes',
        ]);

        app()->setLocale($locale);
        [$pdf] = InvoicePdf::receipt($payment);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public static function locales(): array
    {
        return ['french' => ['fr'], 'arabic' => ['ar'], 'darija' => ['ary']];
    }

    public function test_a_named_client_is_still_kept_and_billed(): void
    {
        $client = Client::create(['raison_sociale' => 'CAFE ATLAS']);
        $invoice = $this->walkInInvoice();
        $invoice->update(['client_id' => $client->id]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'date_paiement' => now(),
            'montant' => 5,
            'mode' => 'especes',
        ]);

        $this->assertSame('CAFE ATLAS', $invoice->refresh()->clientNom());
        $this->assertEquals(15.00, $client->refresh()->solde, 'the balance still follows a named client');
    }

    public function test_deleting_a_client_keeps_their_invoices(): void
    {
        $client = Client::create(['raison_sociale' => 'CAFE ATLAS']);
        $invoice = $this->walkInInvoice();
        $invoice->update(['client_id' => $client->id]);

        $client->delete();

        // The sale happened: it must survive as a walk-in rather than
        // disappearing from the books with the client record.
        $invoice->refresh();
        $this->assertNotNull($invoice->id);
        $this->assertNull($invoice->client_id);
        $this->assertSame(__('app.vente.client_passage'), $invoice->clientNom());
    }
}
