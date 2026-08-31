<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use App\Support\Units;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleUniteTest extends TestCase
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

    public function test_an_article_is_saved_with_a_unit_from_the_list(): void
    {
        Livewire::test(CreateArticle::class)
            ->fillForm([
                'reference' => 'ART-U-1',
                'designation' => 'Farine',
                'prix_vente' => 15,
                'unite' => 'Kg',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Kg', Article::where('reference', 'ART-U-1')->value('unite'));
    }

    public function test_a_unit_outside_the_list_is_rejected(): void
    {
        Livewire::test(CreateArticle::class)
            ->fillForm([
                'reference' => 'ART-U-2',
                'designation' => 'Farine',
                'prix_vente' => 15,
                'unite' => 'Tonneau',
            ])
            ->call('create')
            ->assertHasFormErrors(['unite']);

        $this->assertDatabaseMissing('articles', ['reference' => 'ART-U-2']);
    }

    public function test_a_new_article_starts_on_the_default_unit(): void
    {
        Livewire::test(CreateArticle::class)
            ->assertFormSet(['unite' => 'Unite']);
    }

    /**
     * Articles predate the fixed list, so an edit must not silently drop a
     * unit the list does not carry.
     */
    public function test_editing_keeps_a_unit_saved_before_the_list_existed(): void
    {
        $article = Article::create([
            'reference' => 'ART-U-3',
            'designation' => 'Huile en vrac',
            'prix_vente' => 30,
            'unite' => 'Bidon',
        ]);

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertFormSet(['unite' => 'Bidon'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Bidon', $article->fresh()->unite);
    }

    #[DataProvider('locales')]
    public function test_every_unit_is_translated(string $locale): void
    {
        $this->app->setLocale($locale);

        foreach (Units::KEYS as $key) {
            $this->assertNotSame(
                'app.unite.'.$key,
                Units::label($key),
                "Unit {$key} has no {$locale} translation.",
            );
        }
    }

    public static function locales(): array
    {
        return ['french' => ['fr'], 'arabic' => ['ar'], 'darija' => ['ary']];
    }
}
