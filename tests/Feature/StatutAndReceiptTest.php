<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatutAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $this->client = Client::create(['raison_sociale' => 'CLIENT']);
    }

    private function invoiceOf(float $ttc): Invoice
    {
        $article = Article::create([
            'reference' => 'S-'.uniqid(),
            'designation' => 'Article',
            'prix_vente' => $ttc,
            'stock' => 100,
            'tva' => 0,
        ]);

        $invoice = Invoice::create([
            'numero' => Invoice::nextNumero(),
            'client_id' => $this->client->id,
            'date_facture' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'article_id' => $article->id,
            'designation' => 'Article',
            'quantite' => 1,
            'prix_unitaire' => $ttc,
            'tva' => 0,
        ]);

        return $invoice->refresh();
    }

    private function pay(Invoice $invoice, float $montant): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $this->client->id,
            'date_paiement' => now(),
            'montant' => $montant,
            'mode' => 'especes',
        ]);
    }

    public function test_a_new_invoice_starts_validee(): void
    {
        $invoice = $this->invoiceOf(1000);

        $this->assertSame('validee', $invoice->statut);
        $this->assertEquals(1000.0, $invoice->reste());
    }

    public function test_part_payment_makes_it_partielle(): void
    {
        $invoice = $this->invoiceOf(1000);
        $this->pay($invoice, 400);

        $this->assertSame('partielle', $invoice->refresh()->statut);
        $this->assertEquals(600.0, $invoice->reste());
    }

    public function test_full_payment_makes_it_payee_with_zero_left(): void
    {
        $invoice = $this->invoiceOf(1000);
        $this->pay($invoice, 1000);

        $invoice->refresh();
        $this->assertSame('payee', $invoice->statut);
        $this->assertEquals(0.0, $invoice->reste());
    }

    public function test_paying_the_rest_moves_partielle_to_payee(): void
    {
        $invoice = $this->invoiceOf(1000);
        $this->pay($invoice, 400);
        $this->assertSame('partielle', $invoice->refresh()->statut);

        $this->pay($invoice, 600);
        $this->assertSame('payee', $invoice->refresh()->statut);
        $this->assertEquals(0.0, $invoice->refresh()->reste());
    }

    public function test_removing_a_payment_falls_back_down_the_ladder(): void
    {
        $invoice = $this->invoiceOf(1000);
        $first = $this->pay($invoice, 400);
        $second = $this->pay($invoice, 600);
        $this->assertSame('payee', $invoice->refresh()->statut);

        $second->delete();
        $this->assertSame('partielle', $invoice->refresh()->statut);

        $first->delete();
        $this->assertSame('validee', $invoice->refresh()->statut);
    }

    public function test_only_three_statuses_can_be_stored(): void
    {
        $this->assertSame(['validee', 'partielle', 'payee'], Invoice::STATUTS);

        $column = DB::selectOne('SHOW COLUMNS FROM invoices WHERE Field = ?', ['statut']);

        foreach (['validee', 'partielle', 'payee'] as $allowed) {
            $this->assertStringContainsString($allowed, $column->Type);
        }

        foreach (['brouillon', 'annulee'] as $removed) {
            $this->assertStringNotContainsString($removed, $column->Type, "{$removed} is gone");
        }
    }

    public function test_receipt_shows_only_the_amount_handed_over(): void
    {
        $invoice = $this->invoiceOf(1000);
        $payment = $this->pay($invoice, 400);

        $response = $this->get(route('payment.pdf', $payment));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $html = view('pdf.receipt', [
            'payment' => $payment->load(['client', 'invoice']),
            'societe' => ['nom' => 'MA SOCIETE', 'adresse' => '', 'telephone' => '', 'email' => '', 'ice' => '', 'rc' => ''],
            'devise' => 'DH',
        ])->render();

        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        // The receipt is for this visit: 400 handed over, 600 still owed.
        $this->assertStringContainsString('400,00', $text, 'amount received');
        $this->assertStringContainsString('600,00', $text, 'balance still due');
        $this->assertStringContainsString('1 000,00', $text, 'invoice total for context');
    }

    public function test_receipt_marks_a_fully_settled_invoice(): void
    {
        $invoice = $this->invoiceOf(1000);
        $payment = $this->pay($invoice, 1000);

        $html = view('pdf.receipt', [
            'payment' => $payment->load(['client', 'invoice']),
            'societe' => ['nom' => 'MA SOCIETE', 'adresse' => '', 'telephone' => '', 'email' => '', 'ice' => '', 'rc' => ''],
            'devise' => 'DH',
        ])->render();

        $this->assertStringContainsString(__('app.receipt.solde'), strip_tags($html));
    }
}
