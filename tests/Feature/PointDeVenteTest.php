<?php

namespace Tests\Feature;

use App\Filament\Pages\PointDeVente;
use App\Livewire\PosGrille;
use App\Models\Article;
use App\Models\Caisse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The till screen: what the scanner or a tap puts in the cart must show up
 * at once, and "Encaisser" must produce the same sale, payment, stock move
 * and drawer entry as the Ventes form.
 */
class PointDeVenteTest extends TestCase
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

    public function test_the_page_renders_with_the_scan_box_and_the_products(): void
    {
        $article = $this->article(['designation' => 'Huile 5L']);

        Livewire::test(PointDeVente::class)
            ->assertOk()
            ->assertSee('Huile 5L')
            ->assertSeeHtml('wire:keydown.enter.prevent="scanner"');

        $this->get('/admin/pos')->assertOk()->assertSee($article->designation);
    }

    public function test_a_scan_lands_in_the_cart_and_clears_the_box(): void
    {
        $article = $this->article(['designation' => 'Lait 1L', 'prix_vente' => 12]);

        $component = Livewire::test(PointDeVente::class)
            ->set('scan', $article->code_barre)
            ->call('scanner')
            ->assertSet('scan', '')
            ->assertSee('Lait 1L');

        $lines = array_values($component->get('panier'));

        $this->assertCount(1, $lines);
        $this->assertSame($article->id, (int) $lines[0]['article_id']);
        $this->assertEquals(1, $lines[0]['quantite']);
        $this->assertEquals(12, $lines[0]['prix_unitaire']);
    }

    public function test_tapping_a_tile_adds_the_article_and_a_repeat_bumps_the_quantity(): void
    {
        $article = $this->article();

        $lines = array_values(
            Livewire::test(PointDeVente::class)
                ->call('ajouter', $article->id)
                ->call('ajouter', $article->id)
                ->set('scan', $article->code_barre)
                ->call('scanner')
                ->get('panier')
        );

        $this->assertCount(1, $lines, 'the same article stays on one line');
        $this->assertEquals(3, $lines[0]['quantite']);
    }

    public function test_plus_minus_and_remove_edit_the_line(): void
    {
        $article = $this->article();

        $component = Livewire::test(PointDeVente::class)->call('ajouter', $article->id);
        $key = array_key_first($component->get('panier'));

        $component->call('plus', $key);
        $this->assertEquals(2, $component->get('panier')[$key]['quantite']);

        $component->call('quantite', $key, '5');
        $this->assertEquals(5, $component->get('panier')[$key]['quantite']);

        $component->call('moins', $key);
        $this->assertEquals(4, $component->get('panier')[$key]['quantite']);

        $component->call('retirer', $key);
        $this->assertSame([], $component->get('panier'));
    }

    public function test_minus_on_a_single_unit_removes_the_line(): void
    {
        $article = $this->article();

        $component = Livewire::test(PointDeVente::class)->call('ajouter', $article->id);
        $key = array_key_first($component->get('panier'));

        $component->call('moins', $key);

        $this->assertSame([], $component->get('panier'));
    }

    public function test_an_unknown_code_adds_nothing(): void
    {
        $component = Livewire::test(PointDeVente::class)
            ->set('scan', '9999999999999')
            ->call('scanner')
            ->assertSet('scan', '')
            ->assertNotified();

        $this->assertSame([], $component->get('panier'));
    }

    public function test_encaisser_saves_the_sale_takes_the_cash_and_moves_the_stock(): void
    {
        $lait = $this->article(['designation' => 'Lait 1L', 'prix_vente' => 10, 'stock' => 100, 'tva' => 20]);
        $pain = $this->article(['designation' => 'Pain', 'prix_vente' => 2, 'stock' => 50, 'tva' => 0]);

        $component = Livewire::test(PointDeVente::class)
            ->set('scan', $lait->code_barre)->call('scanner')
            ->set('scan', $lait->code_barre)->call('scanner')
            ->call('ajouter', $pain->id)
            ->set('mode', 'especes')
            ->set('montant_recu', '50')
            ->call('encaisser')
            ->assertNotified();

        // 2 × 10 × 1.20 + 1 × 2 = 26
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals(26, $invoice->total_ttc);
        $this->assertEquals(26, $invoice->montant_paye);
        $this->assertSame('payee', $invoice->statut);
        $this->assertCount(2, $invoice->items);

        $this->assertEquals(98, $lait->fresh()->stock);
        $this->assertEquals(49, $pain->fresh()->stock);

        // The payment is capped at the total; the rest is change, not revenue.
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertEquals(26, $payment->montant);
        $this->assertSame('especes', $payment->mode);
        $this->assertEquals(26, Caisse::solde(), 'cash landed in the drawer');

        // Ready for the next customer.
        $this->assertSame([], $component->get('panier'));
        $this->assertSame('', $component->get('montant_recu'));
    }

    public function test_change_is_computed_from_what_the_client_hands_over(): void
    {
        $article = $this->article(['prix_vente' => 10, 'tva' => 0]);

        $component = Livewire::test(PointDeVente::class)
            ->call('ajouter', $article->id)
            ->set('montant_recu', '50');

        $this->assertEquals(40, $component->instance()->monnaie());
        $this->assertEquals(0, $component->instance()->reste());

        $component->set('montant_recu', '4');

        $this->assertEquals(0, $component->instance()->monnaie());
        $this->assertEquals(6, $component->instance()->reste());
    }

    public function test_a_partial_amount_leaves_the_sale_partielle(): void
    {
        $article = $this->article(['prix_vente' => 10, 'tva' => 0]);

        Livewire::test(PointDeVente::class)
            ->call('ajouter', $article->id)
            ->set('montant_recu', '4')
            ->call('encaisser');

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals(4, $invoice->montant_paye);
        $this->assertSame('partielle', $invoice->statut);
    }

    public function test_enregistrer_saves_on_credit_without_a_payment(): void
    {
        $article = $this->article(['prix_vente' => 10, 'stock' => 5]);

        Livewire::test(PointDeVente::class)
            ->call('ajouter', $article->id)
            ->call('enregistrer')
            ->assertNotified();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('validee', $invoice->statut);
        $this->assertEquals(0, $invoice->montant_paye);
        $this->assertSame(0, Payment::count());
        $this->assertEquals(4, $article->fresh()->stock);
    }

    public function test_an_empty_cart_cannot_be_saved(): void
    {
        Livewire::test(PointDeVente::class)
            ->call('encaisser')
            ->assertNotified();

        $this->assertSame(0, Invoice::count());
    }

    public function test_the_search_narrows_the_grid(): void
    {
        $this->article(['designation' => 'Huile 5L']);
        $this->article(['designation' => 'Sucre 1kg']);

        // The grid is its own component so cart actions never re-render it.
        Livewire::test(PosGrille::class)
            ->set('recherche', 'Huile')
            ->assertSee('Huile 5L')
            ->assertDontSee('Sucre 1kg');
    }

    public function test_a_tile_calls_the_page_not_the_grid(): void
    {
        $article = $this->article();

        // One request straight to the page; the grid stays untouched.
        Livewire::test(PosGrille::class)
            ->assertSeeHtml('wire:click="$parent.ajouter('.$article->id.')"');
    }

    public function test_a_cart_action_does_not_re_render_the_grid(): void
    {
        $article = $this->article(['designation' => 'Huile 5L']);

        $component = Livewire::test(PointDeVente::class)->call('ajouter', $article->id);

        // Livewire skips unchanged children: the tiles are not in the
        // response, only the cart is.
        $this->assertStringNotContainsString('pos-tile-name', $component->html());
        $this->assertStringContainsString('pos-line-name', $component->html());
    }

    public static function localeProvider(): array
    {
        return array_map(fn (string $l) => [$l], array_keys(Locales::SUPPORTED));
    }

    #[DataProvider('localeProvider')]
    public function test_the_page_is_labelled_in_every_language(string $locale): void
    {
        $this->article();

        $this->withSession(['locale' => $locale])
            ->get('/admin/pos')
            ->assertOk()
            ->assertSee(__('app.pos.encaisser', [], $locale))
            ->assertDontSee('app.pos.');
    }
}
