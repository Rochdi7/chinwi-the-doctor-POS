<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Caisse;
use App\Models\CaisseMouvement;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SaleService is the one writer of till sales (Livewire till and API). The
 * sale it produces must carry every side effect the observers attach.
 */
class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Caissier',
            'email' => 'caisse@local.test',
            'password' => bcrypt('secret-pass'),
        ]));

        $this->sales = app(SaleService::class);
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'reference' => 'ART-'.uniqid(),
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 100,
            'tva' => 20,
            'actif' => true,
        ], $attributes));
    }

    public function test_a_cash_sale_writes_invoice_payment_stock_drawer_balance_and_audit(): void
    {
        $client = Client::create(['raison_sociale' => 'Epicerie Atlas']);
        $lait = $this->article(['prix_vente' => 10, 'tva' => 20]);
        $pain = $this->article(['designation' => 'Pain', 'prix_vente' => 2, 'tva' => 0, 'stock' => 50]);

        $lignes = $this->sales->lignesDepuisArticles([
            ['article_id' => $lait->id, 'quantite' => 2],
            ['article_id' => $pain->id, 'quantite' => 1],
        ]);

        [$invoice, $payment] = $this->sales->enregistrer($lignes, $client->id, true, null, 'especes');

        // 2 × 10 × 1.20 + 1 × 2 = 26
        $this->assertEquals(26, $invoice->total_ttc);
        $this->assertEquals(26, $invoice->montant_paye);
        $this->assertSame('payee', $invoice->statut);
        $this->assertSame(auth()->id(), $invoice->user_id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $invoice->numero);
        $this->assertCount(2, $invoice->items);

        $this->assertEquals(98, $lait->fresh()->stock);
        $this->assertEquals(49, $pain->fresh()->stock);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(26, $payment->montant);
        $this->assertEquals(26, Caisse::solde());
        $this->assertSame($payment->id, CaisseMouvement::latest('id')->value('payment_id'));

        $this->assertEquals(0, $client->fresh()->solde, 'fully paid: nothing owed');

        $this->assertTrue(ActivityLog::where('subject_type', 'Invoice')->where('subject_id', $invoice->id)->where('event', 'created')->exists());
        $this->assertSame(2, ActivityLog::where('subject_type', 'InvoiceItem')->where('event', 'created')->count());
        $this->assertTrue(ActivityLog::where('subject_type', 'Payment')->where('event', 'created')->exists());
        $this->assertTrue(ActivityLog::where('event', 'caisse.entree')->exists());
    }

    public function test_tpe_does_not_touch_the_drawer(): void
    {
        $lignes = $this->sales->lignesDepuisArticles([['article_id' => $this->article()->id, 'quantite' => 1]]);

        [, $payment] = $this->sales->enregistrer($lignes, null, true, null, 'tpe');

        $this->assertSame('tpe', $payment->mode);
        $this->assertEquals(0, Caisse::solde());
    }

    public function test_a_partial_amount_leaves_the_invoice_partielle_and_the_client_owing(): void
    {
        $client = Client::create(['raison_sociale' => 'Client credit']);
        $lignes = $this->sales->lignesDepuisArticles([['article_id' => $this->article(['tva' => 0])->id, 'quantite' => 3]]);

        [$invoice, $payment] = $this->sales->enregistrer($lignes, $client->id, true, 10.0, 'especes');

        $this->assertSame('partielle', $invoice->statut);
        $this->assertEquals(10, $payment->montant);
        $this->assertEquals(20, $client->fresh()->solde);
    }

    public function test_more_than_the_total_is_capped_the_rest_is_change(): void
    {
        $lignes = $this->sales->lignesDepuisArticles([['article_id' => $this->article(['tva' => 0])->id, 'quantite' => 1]]);

        [, $payment] = $this->sales->enregistrer($lignes, null, true, 50.0, 'especes');

        $this->assertEquals(10, $payment->montant);
        $this->assertEquals(10, Caisse::solde());
    }

    public function test_on_credit_there_is_no_payment(): void
    {
        $lignes = $this->sales->lignesDepuisArticles([['article_id' => $this->article()->id, 'quantite' => 1]]);

        [$invoice, $payment] = $this->sales->enregistrer($lignes, null, false, null, 'especes');

        $this->assertNull($payment);
        $this->assertSame('validee', $invoice->statut);
        $this->assertSame(0, Payment::count());
        $this->assertEquals(0, Caisse::solde());
    }

    public function test_an_empty_sale_is_refused_and_writes_nothing(): void
    {
        try {
            $this->sales->enregistrer([], null, true, null, 'especes');
            $this->fail('empty sale accepted');
        } catch (ValidationException) {
            $this->assertSame(0, Invoice::count());
        }
    }

    public function test_lines_take_price_and_vat_from_the_article_and_refuse_inactive_ones(): void
    {
        $article = $this->article(['prix_vente' => 12.5, 'tva' => 10, 'designation' => 'Huile']);

        $lignes = $this->sales->lignesDepuisArticles([['article_id' => $article->id, 'quantite' => '2']]);

        $this->assertSame([[
            'article_id' => $article->id,
            'designation' => 'Huile',
            'quantite' => 2.0,
            'prix_unitaire' => 12.5,
            'remise' => 0.0,
            'tva' => 10.0,
        ]], $lignes);

        $inactif = $this->article(['actif' => false]);

        $this->expectException(ValidationException::class);
        $this->sales->lignesDepuisArticles([['article_id' => $inactif->id, 'quantite' => 1]]);
    }

    public function test_the_preview_matches_what_is_saved(): void
    {
        // Prices that round differently per line than in total.
        $a = $this->article(['prix_vente' => 3.33, 'tva' => 20]);
        $b = $this->article(['prix_vente' => 1.11, 'tva' => 7]);

        $lignes = $this->sales->lignesDepuisArticles([
            ['article_id' => $a->id, 'quantite' => 3],
            ['article_id' => $b->id, 'quantite' => 7],
        ]);

        $apercu = $this->sales->apercu($lignes);
        [$invoice] = $this->sales->enregistrer($lignes, null, false, null, 'especes');

        $this->assertEquals((float) $invoice->total_ht, $apercu['total_ht']);
        $this->assertEquals((float) $invoice->total_tva, $apercu['total_tva']);
        $this->assertEquals((float) $invoice->total_ttc, $apercu['total_ttc']);
    }

    public function test_rendu_gives_change_or_balance(): void
    {
        $this->assertEquals(['montant_encaisse' => 26.0, 'monnaie' => 24.0, 'reste' => 0.0], $this->sales->rendu(26, 50));
        $this->assertEquals(['montant_encaisse' => 20.0, 'monnaie' => 0.0, 'reste' => 6.0], $this->sales->rendu(26, 20));
        $this->assertEquals(['montant_encaisse' => 26.0, 'monnaie' => 0.0, 'reste' => 0.0], $this->sales->rendu(26, null));
    }
}
