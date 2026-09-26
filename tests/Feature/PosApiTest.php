<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Caisse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ScanQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The JSON API behind the React till: same session auth as the panel, and
 * every figure computed server-side whatever the client sends.
 */
class PosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Caissier',
            'email' => 'caisse@local.test',
            'password' => bcrypt('bonne-cle'),
        ]);
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'reference' => 'ART-'.uniqid(),
            'code_barre' => '2'.random_int(100000000000, 999999999999),
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 100,
            'tva' => 20,
            'actif' => true,
        ], $attributes));
    }

    public function test_till_endpoints_need_a_logged_in_user(): void
    {
        foreach (['/api/pos/init', '/api/pos/articles', '/api/pos/scans'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }

        $this->postJson('/api/pos/ventes', [])->assertUnauthorized();
        $this->getJson('/api/session')->assertOk()->assertJsonPath('user', null);
    }

    public function test_login_uses_the_existing_users_and_refuses_a_wrong_password(): void
    {
        $this->postJson('/api/login', ['email' => 'caisse@local.test', 'password' => 'mauvaise'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
        $this->assertGuest();

        $this->postJson('/api/login', ['email' => 'caisse@local.test', 'password' => 'bonne-cle'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Caissier')
            ->assertJsonPath('messages.pos.encaisser', 'Encaisser');
        $this->assertAuthenticatedAs($this->user);

        $this->postJson('/api/logout')->assertOk()->assertJsonPath('user', null);
        $this->assertGuest();
    }

    public function test_the_language_switch_returns_rtl_and_the_matching_words(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/locale', ['locale' => 'ary'])
            ->assertOk()
            ->assertJsonPath('dir', 'rtl')
            ->assertJsonPath('messages.pos.encaisser', __('app.pos.encaisser', [], 'ary'));

        $this->postJson('/api/locale', ['locale' => 'xx'])->assertStatus(422);
    }

    public function test_articles_are_searched_like_the_livewire_grid(): void
    {
        $this->article(['designation' => 'Huile 5L']);
        $this->article(['designation' => 'Sucre 1kg']);
        $this->article(['designation' => 'Huile cachée', 'actif' => false]);

        $this->actingAs($this->user)
            ->getJson('/api/pos/articles?recherche=huile')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.designation', 'Huile 5L');
    }

    public function test_a_scan_resolves_barcode_and_azerty_codes(): void
    {
        $article = $this->article(['code_barre' => '2345678901234']);

        $this->actingAs($this->user)
            ->postJson('/api/pos/scan', ['code' => '2345678901234'])
            ->assertOk()
            ->assertJsonPath('article.id', $article->id);

        // What a scanner types on a French keyboard left on its US layout.
        $this->postJson('/api/pos/scan', ['code' => 'é"\'(-è_çà&é"\''])
            ->assertOk()
            ->assertJsonPath('article.id', $article->id);

        $this->postJson('/api/pos/scan', ['code' => '0000000000000'])
            ->assertNotFound()
            ->assertJsonPath('article', null);
    }

    public function test_codes_from_a_paired_phone_are_drained_once(): void
    {
        $article = $this->article();
        ScanQueue::push(ScanQueue::tillFor($this->user->id), $article->code_barre);

        $this->actingAs($this->user)
            ->getJson('/api/pos/scans')
            ->assertOk()
            ->assertJsonPath('scans.0.article.id', $article->id);

        $this->getJson('/api/pos/scans')->assertJsonCount(0, 'scans');
    }

    public function test_the_preview_ignores_prices_sent_by_the_client(): void
    {
        $article = $this->article(['prix_vente' => 10, 'tva' => 20]);

        $this->actingAs($this->user)
            ->postJson('/api/pos/apercu', [
                'items' => [['article_id' => $article->id, 'quantite' => 2, 'prix_unitaire' => 0.01]],
                'montant_recu' => 50,
            ])
            ->assertOk()
            ->assertJsonPath('total_ttc', 24)
            ->assertJsonPath('lignes.0.prix_unitaire', 10)
            ->assertJsonPath('rendu.monnaie', 26)
            ->assertJsonPath('rendu.reste', 0);
    }

    public function test_the_preview_warns_on_empty_stock_without_blocking(): void
    {
        $article = $this->article(['stock' => 0]);

        $this->actingAs($this->user)
            ->postJson('/api/pos/apercu', ['items' => [['article_id' => $article->id, 'quantite' => 1]]])
            ->assertOk()
            ->assertJsonCount(1, 'avertissements');
    }

    public function test_encaisser_saves_through_the_sale_service(): void
    {
        $article = $this->article(['prix_vente' => 10, 'tva' => 0, 'stock' => 5]);

        $response = $this->actingAs($this->user)->postJson('/api/pos/ventes', [
            'items' => [['article_id' => $article->id, 'quantite' => 2]],
            'mode' => 'especes',
            'encaisser' => true,
            'montant_recu' => 50,
            'cle' => (string) Str::uuid(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('invoice.statut', 'payee')
            ->assertJsonPath('payment.montant', 20)
            ->assertJsonPath('monnaie', 30);

        $this->assertEquals(3, $article->fresh()->stock);
        $this->assertEquals(20, Caisse::solde());
        $this->assertSame($this->user->id, Invoice::firstOrFail()->user_id);
    }

    public function test_the_same_sale_key_never_sells_twice(): void
    {
        $article = $this->article();
        $payload = [
            'items' => [['article_id' => $article->id, 'quantite' => 1]],
            'mode' => 'especes',
            'encaisser' => true,
            'cle' => (string) Str::uuid(),
        ];

        $first = $this->actingAs($this->user)->postJson('/api/pos/ventes', $payload)->assertCreated();
        $again = $this->postJson('/api/pos/ventes', $payload)->assertOk();

        $this->assertSame($first->json('invoice.numero'), $again->json('invoice.numero'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_bad_input_is_refused_before_anything_is_written(): void
    {
        $inactif = $this->article(['actif' => false]);
        $ok = $this->article();

        $this->actingAs($this->user);

        $base = ['mode' => 'especes', 'encaisser' => true, 'cle' => (string) Str::uuid()];

        $this->postJson('/api/pos/ventes', $base + ['items' => []])->assertStatus(422);
        $this->postJson('/api/pos/ventes', $base + ['items' => [['article_id' => $inactif->id, 'quantite' => 1]]])->assertStatus(422);
        $this->postJson('/api/pos/ventes', ['mode' => 'cheque'] + $base + ['items' => [['article_id' => $ok->id, 'quantite' => 1]]])->assertStatus(422);
        $this->postJson('/api/pos/ventes', $base + ['items' => [['article_id' => $ok->id, 'quantite' => -1]]])->assertStatus(422);

        $this->assertSame(0, Invoice::count());
    }
}
