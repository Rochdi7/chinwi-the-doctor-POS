<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Support\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ean13Decoder;
use Tests\TestCase;

/**
 * The label page is what gets printed and stuck on the shelf, and what a
 * presentation scanner is held up to on screen: big, pixel-exact, and
 * carrying the name and price so a torn label can still be identified.
 */
class ArticleLabelPageTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'reference' => 'ART-0001',
            'code_barre' => Barcode::generate(),
            'designation' => 'Huile 5L',
            'prix_vente' => 89.5,
            'stock' => 5,
        ], $attributes));
    }

    public function test_the_label_page_shows_name_price_and_a_scannable_barcode(): void
    {
        $this->login();
        $article = $this->article();

        $html = $this->get(route('article.label', $article))
            ->assertOk()
            ->assertSee('Huile 5L')
            ->assertSee('ART-0001')
            ->assertSee('89,50')
            ->assertSee($article->code_barre)
            ->getContent();

        // The barcode is embedded, not linked: nothing to fetch, nothing to
        // resample. And it decodes to the article's code.
        $this->assertSame(1, preg_match('#src="data:image/png;base64,([^"]+)"#', $html, $m));
        $this->assertSame($article->code_barre, Ean13Decoder::decode(base64_decode($m[1])));
    }

    public function test_the_label_is_not_resampled_by_the_browser(): void
    {
        $this->login();

        $this->get(route('article.label', $this->article()))
            ->assertOk()
            ->assertSee('image-rendering: pixelated', false);
    }

    public function test_an_article_without_a_code_has_no_label(): void
    {
        $this->login();

        $this->get(route('article.label', $this->article(['code_barre' => null])))
            ->assertNotFound();
    }

    public function test_the_label_needs_a_login(): void
    {
        $article = $this->article();

        $this->get(route('article.label', $article))->assertRedirect();
    }
}
