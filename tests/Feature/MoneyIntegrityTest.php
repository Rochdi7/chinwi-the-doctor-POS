<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a sale, editing a payment and saving an article must leave the
 * stock, the cash drawer and the client balance matching what really happened.
 */
class MoneyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create(['name' => 'Gérant', 'email' => 'gerant@local.test', 'password' => bcrypt('x-secret-1')]));
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'reference' => 'ART-'.uniqid(), 'designation' => 'Lait 1L', 'prix_vente' => 10, 'stock' => 100, 'tva' => 0, 'actif' => true,
        ], $attributes));
    }

    /** A 30 DH sale (3 × 10) of $article, paid as asked. */
    private function sell(Article $article, array $overrides = []): int
    {
        return $this->postJson('/api/ventes', array_merge([
            'numero' => Invoice::nextNumero(),
            'date_facture' => now()->toDateString(),
            'client_id' => null,
            'items' => [['article_id' => $article->id, 'designation' => 'Lait 1L', 'quantite' => 3, 'prix_unitaire' => 10, 'remise' => 0, 'tva' => 0]],
            'encaisser' => true,
            'mode' => 'especes',
            'montant_recu' => null,
        ], $overrides))->assertCreated()->json('invoice.id');
    }

    public function test_deleting_a_sale_gives_back_the_stock_the_cash_and_the_client_balance(): void
    {
        $client = Client::create(['raison_sociale' => 'Atlas', 'actif' => true]);
        $article = $this->article();
        $id = $this->sell($article, ['client_id' => $client->id, 'montant_recu' => 10]);

        $this->assertEquals(97, $article->fresh()->stock);
        $this->assertEquals(10, Caisse::solde());
        $this->assertEquals(20, $client->fresh()->solde);

        $this->deleteJson("/api/ventes/{$id}")->assertNoContent();

        $this->assertEquals(100, $article->fresh()->stock);
        $this->assertEquals(0, Caisse::solde());
        $this->assertEquals(0, $client->fresh()->solde);
        $this->assertSame(0, Payment::count());
    }

    public function test_editing_a_payment_moves_the_drawer_and_a_later_delete_reverses_it_exactly(): void
    {
        $id = $this->sell($this->article(), ['encaisser' => false]);
        $this->postJson("/api/ventes/{$id}/encaisser", ['montant' => 20, 'mode' => 'especes'])->assertCreated();
        $payment = Payment::firstOrFail();
        $this->assertEquals(20, Caisse::solde());

        $edit = fn (array $data) => $this->putJson("/api/reglements/{$payment->id}", array_merge([
            'invoice_id' => $id, 'client_id' => null, 'montant' => 20, 'date_paiement' => now()->toDateString(), 'mode' => 'especes',
        ], $data));

        $edit(['montant' => 25])->assertOk();
        $this->assertEquals(25, Caisse::solde());

        $edit(['montant' => 25, 'mode' => 'tpe'])->assertOk();
        $this->assertEquals(0, Caisse::solde(), 'card money is not in the drawer');

        $edit(['montant' => 15, 'mode' => 'especes'])->assertOk();
        $this->assertEquals(15, Caisse::solde());

        $this->deleteJson("/api/reglements/{$payment->id}")->assertNoContent();
        $this->assertEquals(0, Caisse::solde());
    }

    public function test_a_payment_edit_cannot_go_negative_or_pay_more_than_owed(): void
    {
        $id = $this->sell($this->article(), ['encaisser' => false]);
        $this->postJson("/api/ventes/{$id}/encaisser", ['montant' => 10, 'mode' => 'especes'])->assertCreated();
        $payment = Payment::firstOrFail();

        $edit = fn (float $montant) => $this->putJson("/api/reglements/{$payment->id}", [
            'invoice_id' => $id, 'client_id' => null, 'montant' => $montant, 'date_paiement' => now()->toDateString(), 'mode' => 'especes',
        ]);

        $edit(-5)->assertJsonValidationErrors('montant');
        $edit(31)->assertJsonValidationErrors('montant');
        $edit(30)->assertOk();
        $this->assertSame('payee', Invoice::find($id)->statut);
    }

    public function test_moving_a_payment_to_another_sale_updates_the_one_it_left(): void
    {
        $article = $this->article();
        $first = $this->sell($article);
        $second = $this->sell($article, ['encaisser' => false]);
        $payment = Payment::where('invoice_id', $first)->firstOrFail();

        $this->putJson("/api/reglements/{$payment->id}", [
            'invoice_id' => $second, 'client_id' => null, 'montant' => 30, 'date_paiement' => now()->toDateString(), 'mode' => 'especes',
        ])->assertOk();

        $this->assertEquals(0, Invoice::find($first)->montant_paye);
        $this->assertEquals(30, Invoice::find($second)->montant_paye);
    }

    public function test_a_back_office_line_needs_a_positive_quantity_and_price(): void
    {
        $article = $this->article();

        $this->postJson('/api/ventes', [
            'numero' => Invoice::nextNumero(), 'date_facture' => now()->toDateString(), 'client_id' => null, 'encaisser' => false,
            'items' => [['article_id' => $article->id, 'designation' => 'Lait', 'quantite' => -5, 'prix_unitaire' => -1, 'remise' => 0, 'tva' => 0]],
        ])->assertJsonValidationErrors(['items.0.quantite', 'items.0.prix_unitaire']);

        $this->assertEquals(100, $article->fresh()->stock);
    }

    public function test_saving_an_article_without_its_stock_keeps_what_the_till_sold_meanwhile(): void
    {
        $article = $this->article(['code_barre' => null]);
        $this->sell($article);

        $this->putJson("/api/articles/{$article->id}", [
            'designation' => 'Lait 1L', 'reference' => $article->reference, 'unite' => 'Unite', 'prix_vente' => 12, 'tva' => 0, 'actif' => true,
        ])->assertOk();

        $this->assertEquals(97, $article->fresh()->stock);
        $this->assertEquals(12, $article->fresh()->prix_vente);
    }

    public function test_a_barcode_scanned_into_the_form_on_azerty_is_stored_as_digits(): void
    {
        $this->postJson('/api/articles', [
            'designation' => 'Nutella', 'reference' => 'NUT-1', 'code_barre' => "à&é\"'(-è_çààà", 'unite' => 'Unite', 'prix_vente' => 30, 'actif' => true,
        ])->assertCreated()->assertJsonPath('code_barre', '0123456789000');
    }
}
