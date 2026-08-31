<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryTest extends TestCase
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

    public function test_a_category_can_be_created_from_its_own_page(): void
    {
        Livewire::test(ListCategories::class)
            ->callAction('create', ['nom' => 'Boissons'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('categories', ['nom' => 'Boissons']);
    }

    public function test_two_categories_cannot_share_a_name(): void
    {
        Category::create(['nom' => 'Boissons']);

        Livewire::test(ListCategories::class)
            ->callAction('create', ['nom' => 'Boissons'])
            ->assertHasActionErrors(['nom']);

        $this->assertSame(1, Category::count());
    }

    public function test_an_article_is_filed_under_a_category(): void
    {
        $category = Category::create(['nom' => 'Epicerie']);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'reference' => 'ART-CAT-1',
                'designation' => 'Sucre 1kg',
                'prix_vente' => 12,
                'category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('reference', 'ART-CAT-1')->firstOrFail();

        $this->assertTrue($category->is($article->category));
    }

    public function test_a_category_is_created_without_leaving_the_article_form(): void
    {
        Livewire::test(CreateArticle::class)
            ->fillForm([
                'reference' => 'ART-CAT-2',
                'designation' => 'Lait 1L',
                'prix_vente' => 8,
            ])
            ->callFormComponentAction('category_id', 'createOption', ['nom' => 'Frais'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', ['nom' => 'Frais']);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'reference' => 'ART-CAT-2',
                'designation' => 'Lait 1L',
                'prix_vente' => 8,
                'category_id' => Category::where('nom', 'Frais')->value('id'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Frais', Article::where('reference', 'ART-CAT-2')->first()->category->nom);
    }

    public function test_deleting_a_category_leaves_its_articles_alone(): void
    {
        $category = Category::create(['nom' => 'Divers']);

        $article = Article::create([
            'reference' => 'ART-CAT-3',
            'designation' => 'Article divers',
            'prix_vente' => 5,
            'category_id' => $category->id,
        ]);

        $category->delete();

        $this->assertNull($article->fresh()->category_id);
        $this->assertDatabaseHas('articles', ['reference' => 'ART-CAT-3']);
    }

    public function test_the_article_list_filters_by_category(): void
    {
        $boissons = Category::create(['nom' => 'Boissons']);
        $epicerie = Category::create(['nom' => 'Epicerie']);

        $cola = Article::create([
            'reference' => 'ART-F-1', 'designation' => 'Cola', 'prix_vente' => 10,
            'category_id' => $boissons->id,
        ]);
        $sucre = Article::create([
            'reference' => 'ART-F-2', 'designation' => 'Sucre', 'prix_vente' => 12,
            'category_id' => $epicerie->id,
        ]);

        Livewire::test(ListArticles::class)
            ->filterTable('category_id', $boissons->id)
            ->assertCanSeeTableRecords([$cola])
            ->assertCanNotSeeTableRecords([$sucre]);
    }
}
