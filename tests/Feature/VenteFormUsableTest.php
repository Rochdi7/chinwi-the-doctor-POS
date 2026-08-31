<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The sale form has to be readable by someone who does not read easily:
 * every control says what it does, and the article list is populated
 * before anything is typed.
 */
class VenteFormUsableTest extends TestCase
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

    private function article(string $designation = 'Lait 1L', bool $actif = true): Article
    {
        return Article::create([
            'reference' => 'ART-'.uniqid(),
            'designation' => $designation,
            'prix_vente' => 12,
            'stock' => 40,
            'tva' => 20,
            'actif' => $actif,
        ]);
    }

    public function test_the_articles_are_listed_before_anything_is_typed(): void
    {
        // Without options() the select sat on "En recherche..." forever.
        $this->article('Lait 1L');
        $this->article('Pain complet');

        $html = $this->get('/admin/invoices/create')->assertOk()->getContent();

        $this->assertStringContainsString('Lait 1L', $html);
        $this->assertStringContainsString('Pain complet', $html);
    }

    public function test_an_inactive_article_is_not_offered_for_sale(): void
    {
        $this->article('Produit retiré', actif: false);

        $html = $this->get('/admin/invoices/create')->assertOk()->getContent();

        $this->assertStringNotContainsString('Produit retiré', $html);
    }

    /** Every button and section says what it is, in each language. */
    #[DataProvider('locales')]
    public function test_the_add_button_is_written_out_not_a_bare_plus(string $locale): void
    {
        $this->article();
        session(['locale' => $locale]);
        app()->setLocale($locale);

        $html = $this->get('/admin/invoices/create')->assertOk()->getContent();

        foreach (['app.invoice.ajouter_article', 'app.invoice.items', 'app.invoice.items_aide'] as $key) {
            $label = __($key);

            $this->assertNotSame($key, $label, "{$key} is translated in {$locale}");
            $this->assertStringContainsString($label, $html, "{$key} shown in {$locale}");
        }
    }

    #[DataProvider('locales')]
    public function test_the_extra_article_section_is_named(string $locale): void
    {
        $this->article();
        session(['locale' => $locale]);
        app()->setLocale($locale);

        $html = $this->get('/admin/articles/create')->assertOk()->getContent();

        // This section used to be titled literally "+".
        $this->assertStringContainsString(__('app.article.section_infos'), $html);
        $this->assertStringContainsString(__('app.article.section_infos_aide'), $html);
    }

    public static function locales(): array
    {
        return ['french' => ['fr'], 'arabic' => ['ar'], 'darija' => ['ary']];
    }
}
