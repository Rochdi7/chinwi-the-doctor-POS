<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Ajout rapide: scan a manufacturer barcode, add a photo, save. */
class QuickAddArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.product_lookup.enabled' => false]);
        Storage::fake(Article::IMAGE_DISK);

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@local.test', 'password' => bcrypt('admin1234')]));
    }

    public function test_a_scanned_product_is_saved_with_its_barcode_photo_and_zero_vat(): void
    {
        Livewire::test(ListArticles::class)
            ->mountAction('ajoutRapide')
            ->setActionData([
                'code_barre' => '0062325614010',
                'designation' => 'Verre trempé SAINYOU ESR Super X',
                'prix_vente' => 50,
                'image' => [UploadedFile::fake()->image('verre.jpg', 800, 1200)],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $article = Article::where('code_barre', '0062325614010')->firstOrFail();

        $this->assertSame('Verre trempé SAINYOU ESR Super X', $article->designation);
        $this->assertEquals(0, $article->tva);
        $this->assertEquals(1, $article->stock);
        $this->assertStringStartsWith('ART-', $article->reference);
        $this->assertNotNull($article->image);
        Storage::disk(Article::IMAGE_DISK)->assertExists($article->image);
        $this->assertStringEndsWith('/storage/'.$article->image, $article->imageUrl());
    }

    public function test_the_vat_default_follows_the_settings(): void
    {
        Setting::put('tva_defaut', '10');

        Livewire::test(ListArticles::class)
            ->mountAction('ajoutRapide')
            ->assertActionDataSet(['tva' => 10.0]);
    }

    public function test_a_barcode_already_in_the_catalogue_is_refused(): void
    {
        Article::create(['reference' => 'ART-1', 'designation' => 'Déjà là', 'code_barre' => '0062325614010', 'prix_vente' => 10]);

        Livewire::test(ListArticles::class)
            ->mountAction('ajoutRapide')
            ->setActionData(['code_barre' => '0062325614010', 'designation' => 'Doublon', 'prix_vente' => 10])
            ->callMountedAction()
            ->assertHasActionErrors(['code_barre' => 'unique']);
    }

    public function test_replacing_or_deleting_the_photo_removes_the_old_file(): void
    {
        $disk = Storage::disk(Article::IMAGE_DISK);
        $disk->put('articles/a.jpg', 'x');
        $disk->put('articles/b.jpg', 'y');

        $article = Article::create(['reference' => 'ART-1', 'designation' => 'X', 'prix_vente' => 1, 'image' => 'articles/a.jpg']);
        $article->update(['image' => 'articles/b.jpg']);
        $disk->assertMissing('articles/a.jpg');

        $article->delete();
        $disk->assertMissing('articles/b.jpg');
    }
}
