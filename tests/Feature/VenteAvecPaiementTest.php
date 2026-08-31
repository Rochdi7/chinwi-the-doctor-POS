<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Article;
use App\Models\Caisse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class VenteAvecPaiementTest extends TestCase
{
    use RefreshDatabase;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Caissier',
            'email' => 'caissier@local.test',
            'password' => bcrypt('secret1234'),
        ]));

        $this->article = Article::create([
            'reference' => 'ART-0001',
            'designation' => 'Lait 1L',
            'prix_vente' => 50,
            'stock' => 10,
            'tva' => 0,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function createVente(array $extra = []): Testable
    {
        return Livewire::test(CreateInvoice::class)
            ->fillForm(array_merge([
                'client_id' => null,
                'date_facture' => now()->toDateString(),
                'numero' => Invoice::nextNumero(),
                'items' => [
                    [
                        'article_id' => $this->article->id,
                        'designation' => 'Lait 1L',
                        'quantite' => 2,
                        'prix_unitaire' => 50,
                        'remise' => 0,
                        'tva' => 0,
                    ],
                ],
            ], $extra))
            ->call('create');
    }

    public function test_a_sale_paid_in_full_at_the_counter_settles_immediately(): void
    {
        $this->createVente([
            'encaisser_maintenant' => true,
            'mode_paiement' => 'especes',
            'montant_recu' => null,
        ])->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertEquals(100.00, $invoice->total_ttc);
        $this->assertSame('payee', $invoice->statut, 'blank amount means paid in full');
        $this->assertEquals(0.0, $invoice->reste());

        $this->assertSame(1, Payment::count());
        $this->assertEquals(100.00, Caisse::solde(), 'cash reached the drawer');
    }

    public function test_a_deposit_leaves_the_sale_partielle(): void
    {
        $this->createVente([
            'encaisser_maintenant' => true,
            'mode_paiement' => 'especes',
            'montant_recu' => 30,
        ])->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertSame('partielle', $invoice->statut);
        $this->assertEquals(70.0, $invoice->reste());
        $this->assertEquals(30.00, Caisse::solde());
    }

    public function test_a_credit_sale_records_no_payment(): void
    {
        $this->createVente(['encaisser_maintenant' => false])->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertSame('validee', $invoice->statut);
        $this->assertEquals(100.0, $invoice->reste());
        $this->assertSame(0, Payment::count());
        $this->assertEquals(0.0, Caisse::solde());
    }

    public function test_a_card_sale_does_not_touch_the_cash_drawer(): void
    {
        $this->createVente([
            'encaisser_maintenant' => true,
            'mode_paiement' => 'tpe',
            'montant_recu' => null,
        ])->assertHasNoFormErrors();

        $this->assertSame('payee', Invoice::firstOrFail()->statut);
        $this->assertEquals(0.0, Caisse::solde(), 'TPE money is not in the till');
    }

    public function test_an_overpayment_is_capped_at_the_total(): void
    {
        // The drawer must never record more than the sale was worth.
        $this->createVente([
            'encaisser_maintenant' => true,
            'mode_paiement' => 'especes',
            'montant_recu' => 500,
        ])->assertHasNoFormErrors();

        $this->assertEquals(100.00, Payment::firstOrFail()->montant);
        $this->assertEquals(100.00, Caisse::solde());
        $this->assertSame('payee', Invoice::firstOrFail()->statut);
    }

    public function test_selling_still_moves_stock(): void
    {
        $this->createVente([
            'encaisser_maintenant' => true,
            'mode_paiement' => 'especes',
        ])->assertHasNoFormErrors();

        $this->assertEquals(8.0, (float) $this->article->fresh()->stock);
    }
}
