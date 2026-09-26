<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Caisse;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\ScanQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The back-office API behind the React screens. Carries the rules the
 * Filament resources enforced (ArticleUniteTest, CategoryTest,
 * VenteAvecPaiementTest, StockIntegrityTest, StatutAndReceiptTest...).
 */
class BackOfficeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Gérant', 'email' => 'gerant@local.test', 'password' => bcrypt('x-secret-1')]);
        $this->actingAs($this->user);
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'reference' => 'ART-'.uniqid(),
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 100,
            'tva' => 0,
            'actif' => true,
        ], $attributes));
    }

    private function sale(array $overrides = []): array
    {
        $a = $this->article(['prix_vente' => 10, 'tva' => 0]);

        return array_merge([
            'numero' => Invoice::nextNumero(),
            'date_facture' => now()->toDateString(),
            'client_id' => null,
            'items' => [['article_id' => $a->id, 'designation' => 'Lait 1L', 'quantite' => 3, 'prix_unitaire' => 10, 'remise' => 0, 'tva' => 0]],
            'encaisser' => true,
            'mode' => 'especes',
            'montant_recu' => null,
        ], $overrides);
    }

    public function test_everything_needs_a_login(): void
    {
        auth()->logout();

        foreach (['/api/dashboard', '/api/articles', '/api/categories', '/api/clients', '/api/ventes', '/api/reglements', '/api/caisse', '/api/journal', '/api/parametres'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    // ---- Articles ---------------------------------------------------------------

    public function test_a_new_article_gets_a_valid_generated_barcode_and_the_default_unit(): void
    {
        $defaults = $this->getJson('/api/articles/nouveau')->assertOk()->json();

        $this->assertSame('Piece', $defaults['unite']);
        $this->assertTrue(\App\Support\Barcode::isValidEan13($defaults['code_barre']));
        $this->assertStringStartsWith('2', $defaults['code_barre']);

        $this->postJson('/api/articles', [
            'designation' => 'Huile 5L', 'reference' => $defaults['reference'], 'code_barre' => $defaults['code_barre'],
            'unite' => 'L', 'prix_vente' => 89.9, 'actif' => true,
        ])->assertCreated()->assertJsonPath('unite', 'L')->assertJsonPath('code_barre_standard', true);
    }

    public function test_article_rules_unit_list_checksum_and_uniqueness(): void
    {
        $existing = $this->article(['reference' => 'REF-1', 'code_barre' => '2000000000015']);
        $base = ['designation' => 'X', 'prix_vente' => 1, 'unite' => 'Kg'];

        $this->postJson('/api/articles', array_merge($base, ['reference' => 'R2', 'unite' => 'Tonne']))->assertJsonValidationErrors('unite');
        $this->postJson('/api/articles', array_merge($base, ['reference' => 'R3', 'code_barre' => '2000000000016']))->assertJsonValidationErrors('code_barre');
        $this->postJson('/api/articles', array_merge($base, ['reference' => 'REF-1']))->assertJsonValidationErrors('reference');
        $this->postJson('/api/articles', array_merge($base, ['reference' => 'R4', 'code_barre' => '2000000000015']))->assertJsonValidationErrors('code_barre');

        // A unit saved before the list existed is kept on edit.
        $existing->forceFill(['unite' => 'Fardeau'])->save();
        $this->putJson("/api/articles/{$existing->id}", ['designation' => 'Y', 'reference' => 'REF-1', 'unite' => 'Fardeau', 'prix_vente' => 2])
            ->assertOk()->assertJsonPath('unite', 'Fardeau');
    }

    public function test_articles_are_searched_filtered_and_paginated(): void
    {
        $cat = Category::create(['nom' => 'Boissons']);
        $this->article(['designation' => 'Eau Sidi Ali', 'category_id' => $cat->id]);
        $this->article(['designation' => 'Huile', 'stock' => 0]);

        $this->getJson('/api/articles?q=sidi')->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'Boissons');
        $this->getJson("/api/articles?categorie={$cat->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/articles?stock=rupture')->assertJsonCount(1, 'data')->assertJsonPath('data.0.designation', 'Huile');
        $this->getJson('/api/articles?per_page=5')->assertJsonPath('per_page', 5)->assertJsonPath('total', 2);
    }

    public function test_a_product_photo_is_stored_replaced_and_removed(): void
    {
        \Illuminate\Support\Facades\Storage::fake(Article::IMAGE_DISK);
        $article = $this->article();

        $this->assertSame('Piece', $this->getJson('/api/articles/nouveau')->json('unite'));

        $first = $this->postJson("/api/articles/{$article->id}/image", ['image' => \Illuminate\Http\UploadedFile::fake()->image('p.png', 200, 200)])
            ->assertOk()->json();
        $this->assertNotNull($first['image_url']);
        \Illuminate\Support\Facades\Storage::disk(Article::IMAGE_DISK)->assertExists($article->fresh()->image);

        $old = $article->fresh()->image;
        $this->postJson("/api/articles/{$article->id}/image", ['image' => \Illuminate\Http\UploadedFile::fake()->image('q.png', 200, 200)])->assertOk();
        \Illuminate\Support\Facades\Storage::disk(Article::IMAGE_DISK)->assertMissing($old);

        $this->postJson("/api/articles/{$article->id}/image", ['image' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('image');

        $this->deleteJson("/api/articles/{$article->id}/image")->assertOk()->assertJsonPath('image_url', null);
        $this->assertNull($article->fresh()->image);
    }

    // ---- Categories -------------------------------------------------------------

    public function test_categories_are_unique_and_deleting_one_keeps_its_articles(): void
    {
        $id = $this->postJson('/api/categories', ['nom' => 'Épicerie'])->assertCreated()->json('id');
        $this->postJson('/api/categories', ['nom' => 'Épicerie'])->assertJsonValidationErrors('nom');

        $article = $this->article(['category_id' => $id]);
        $this->getJson('/api/categories')->assertJsonPath('data.0.articles_count', 1);

        $this->deleteJson("/api/categories/{$id}")->assertNoContent();
        $this->assertNull($article->fresh()->category_id);
    }

    // ---- Clients ----------------------------------------------------------------

    public function test_clients_crud_and_detail_with_their_sales(): void
    {
        $id = $this->postJson('/api/clients', ['raison_sociale' => 'Épicerie Atlas', 'telephone' => '0600000000', 'actif' => true])
            ->assertCreated()->json('id');
        $this->postJson('/api/clients', ['raison_sociale' => ''])->assertJsonValidationErrors('raison_sociale');

        $this->postJson('/api/ventes', $this->sale(['client_id' => $id, 'montant_recu' => 10]))->assertCreated();

        $this->getJson("/api/clients/{$id}")->assertOk()
            ->assertJsonPath('solde', 20)
            ->assertJsonCount(1, 'invoices')
            ->assertJsonCount(1, 'payments');
        $this->getJson('/api/clients?doit=1')->assertJsonCount(1, 'data');
    }

    // ---- Ventes -----------------------------------------------------------------

    public function test_a_sale_paid_in_full_deposit_credit_card_and_overpayment(): void
    {
        $full = $this->postJson('/api/ventes', $this->sale())->assertCreated();
        $this->assertSame('payee', $full->json('invoice.statut'));
        $this->assertEquals(30, Caisse::solde());

        $deposit = $this->postJson('/api/ventes', $this->sale(['montant_recu' => 10]))->assertCreated();
        $this->assertSame('partielle', $deposit->json('invoice.statut'));

        $credit = $this->postJson('/api/ventes', $this->sale(['encaisser' => false]))->assertCreated();
        $this->assertSame('validee', $credit->json('invoice.statut'));
        $this->assertNull($credit->json('payment'));

        $this->postJson('/api/ventes', $this->sale(['mode' => 'tpe']))->assertCreated();
        $this->assertEquals(40, Caisse::solde(), 'card sale leaves the drawer alone');

        $over = $this->postJson('/api/ventes', $this->sale(['montant_recu' => 500]))->assertCreated();
        $this->assertEquals(30, $over->json('payment.montant'), 'capped at the total');
    }

    public function test_editing_a_sale_moves_only_the_stock_delta(): void
    {
        $lait = $this->article(['stock' => 100]);
        $pain = $this->article(['designation' => 'Pain', 'stock' => 50]);

        $id = $this->postJson('/api/ventes', $this->sale([
            'items' => [['article_id' => $lait->id, 'designation' => 'Lait', 'quantite' => 5, 'prix_unitaire' => 10, 'tva' => 0]],
            'encaisser' => false,
        ]))->json('invoice.id');
        $this->assertEquals(95, $lait->fresh()->stock);

        $line = Invoice::find($id)->items()->first();
        $payload = fn (array $items) => ['numero' => Invoice::find($id)->numero, 'date_facture' => now()->toDateString(), 'items' => $items];

        // Price change only: no stock move.
        $this->putJson("/api/ventes/{$id}", $payload([['id' => $line->id, 'article_id' => $lait->id, 'designation' => 'Lait', 'quantite' => 5, 'prix_unitaire' => 12, 'tva' => 0]]))->assertOk();
        $this->assertEquals(95, $lait->fresh()->stock);

        // Quantity 5 -> 2: three come back.
        $this->putJson("/api/ventes/{$id}", $payload([['id' => $line->id, 'article_id' => $lait->id, 'designation' => 'Lait', 'quantite' => 2, 'prix_unitaire' => 12, 'tva' => 0]]))->assertOk()
            ->assertJsonPath('total_ttc', 24);
        $this->assertEquals(98, $lait->fresh()->stock);

        // Line replaced by another article: lait returns, pain leaves.
        $this->putJson("/api/ventes/{$id}", $payload([['article_id' => $pain->id, 'designation' => 'Pain', 'quantite' => 4, 'prix_unitaire' => 2, 'tva' => 0]]))->assertOk();
        $this->assertEquals(100, $lait->fresh()->stock);
        $this->assertEquals(46, $pain->fresh()->stock);
    }

    public function test_a_discount_larger_than_the_line_is_refused(): void
    {
        $this->postJson('/api/ventes', $this->sale([
            'items' => [['designation' => 'Service', 'quantite' => 1, 'prix_unitaire' => 10, 'remise' => 15, 'tva' => 0]],
        ]))->assertJsonValidationErrors('items.0.remise');

        $this->assertSame(0, Invoice::count());
    }

    public function test_encaisser_is_limited_to_what_is_owed_and_climbs_the_statuses(): void
    {
        $id = $this->postJson('/api/ventes', $this->sale(['encaisser' => false]))->json('invoice.id');

        $this->postJson("/api/ventes/{$id}/encaisser", ['montant' => 31, 'mode' => 'especes'])->assertJsonValidationErrors('montant');
        $this->postJson("/api/ventes/{$id}/encaisser", ['montant' => 10, 'mode' => 'especes'])->assertCreated()->assertJsonPath('invoice.statut', 'partielle');
        $this->postJson("/api/ventes/{$id}/encaisser", ['montant' => 20, 'mode' => 'tpe'])->assertCreated()->assertJsonPath('invoice.statut', 'payee')->assertJsonPath('invoice.reste', 0);

        $detail = $this->getJson("/api/ventes/{$id}")->assertOk();
        $this->assertCount(2, $detail->json('payments'));

        // Removing a payment falls back down the ladder.
        $this->deleteJson('/api/reglements/'.$detail->json('payments.0.id'))->assertNoContent();
        $this->assertSame('partielle', Invoice::find($id)->statut);
    }

    public function test_sales_list_filters_by_status_and_client(): void
    {
        $client = Client::create(['raison_sociale' => 'Atlas']);
        $this->postJson('/api/ventes', $this->sale(['client_id' => $client->id]));
        $this->postJson('/api/ventes', $this->sale(['encaisser' => false]));

        $this->getJson('/api/ventes?statut=validee')->assertJsonCount(1, 'data');
        $this->getJson("/api/ventes?client_id={$client->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.client', 'Atlas');
        $this->getJson('/api/ventes?q=Atlas')->assertJsonCount(1, 'data');
    }

    // ---- Dashboard, caisse, journal, settings ------------------------------------

    public function test_dashboard_figures_and_the_period_filter(): void
    {
        $this->postJson('/api/ventes', $this->sale(['montant_recu' => 10]));
        $old = $this->postJson('/api/ventes', $this->sale(['date_facture' => now()->subYear()->toDateString(), 'encaisser' => false]));

        $all = $this->getJson('/api/dashboard')->assertOk();
        $this->assertEquals(60, $all->json('stats.ca'));
        $this->assertEquals(10, $all->json('stats.regle'));
        $this->assertEquals(50, $all->json('stats.impaye'));
        $this->assertEquals(10, $all->json('stats.caisse'));
        $this->assertCount(12, $all->json('mensuel.labels'));

        $recent = $this->getJson('/api/dashboard?du='.now()->startOfMonth()->toDateString())->assertOk();
        $this->assertEquals(30, $recent->json('stats.ca'));
        $this->assertEquals(20, $recent->json('stats.impaye'));

        $this->getJson('/api/dashboard/impaye')->assertOk()->assertJsonPath('count', 2)->assertJsonPath('total', 50);
        $this->getJson('/api/dashboard/nope')->assertNotFound();
    }

    public function test_caisse_journal_shows_each_cash_movement_and_the_balance(): void
    {
        $this->postJson('/api/ventes', $this->sale());

        $this->getJson('/api/caisse')->assertOk()
            ->assertJsonPath('solde', 30)
            ->assertJsonPath('data.0.type', 'entree')
            ->assertJsonPath('data.0.solde_apres', 30);
    }

    public function test_the_journal_shows_readable_before_after_changes(): void
    {
        $article = $this->article(['prix_vente' => 120]);
        $article->update(['prix_vente' => 99]);

        $row = collect($this->getJson('/api/journal?event=updated')->assertOk()->json('data'))->first();
        $this->assertSame('Article', $row['subject_type']);
        $this->assertNotEmpty($row['changes']);
        $this->assertMatchesRegularExpression('/\d{2}:\d{2}:\d{2}\.\d{3}$/', $row['occurred_at']);

        $this->assertContains('updated', $this->getJson('/api/journal/evenements')->json('data'));
    }

    public function test_alerts_list_unpaid_sales_and_empty_shelves(): void
    {
        $this->article(['designation' => 'Vide', 'stock' => 0]);
        $this->article(['designation' => 'Presque', 'stock' => 3]);
        $this->article(['designation' => 'Inactif vide', 'stock' => 0, 'actif' => false]);
        $this->postJson('/api/ventes', $this->sale(['montant_recu' => 10]));
        $this->postJson('/api/ventes', $this->sale());

        $this->getJson('/api/alertes')->assertOk()
            ->assertJsonPath('impayes.count', 1)
            ->assertJsonPath('impayes.total', 20)
            ->assertJsonPath('impayes.items.0.reste', 20)
            ->assertJsonPath('ruptures.count', 1)
            ->assertJsonPath('ruptures.items.0.designation', 'Vide')
            ->assertJsonPath('stock_bas.count', 1);
    }

    public function test_settings_are_saved_and_logged(): void
    {
        $this->putJson('/api/parametres', ['societe_nom' => 'Chinwi', 'devise' => 'MAD', 'tva_defaut' => 20])->assertOk()->assertJsonPath('devise', 'MAD');
        $this->assertSame('MAD', Setting::get('devise'));
        $this->assertTrue(ActivityLog::where('event', 'settings.updated')->exists());

        $this->putJson('/api/parametres', ['societe_nom' => ''])->assertJsonValidationErrors('societe_nom');
    }

    // ---- Phone scanner --------------------------------------------------------------

    public function test_a_phone_scan_is_named_back_and_queued_for_the_till(): void
    {
        $article = $this->article(['designation' => 'Thé vert', 'code_barre' => '2000000000022']);
        $till = $this->getJson('/api/scanner/till')->json('till');

        $this->postJson('/api/scanner/envoyer', ['code' => '2000000000022', 'till' => $till])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message', 'Thé vert');
        $this->postJson('/api/scanner/envoyer', ['code' => '0000000000000'])->assertJsonPath('ok', false);

        $this->assertSame(['2000000000022'], ScanQueue::drain($till));
    }
}
