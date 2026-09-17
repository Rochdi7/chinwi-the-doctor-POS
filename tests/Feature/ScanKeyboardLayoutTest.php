<?php

namespace Tests\Feature;

use App\Filament\Pages\PointDeVente;
use App\Models\Article;
use App\Models\User;
use App\Support\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A USB scanner presses keys; Windows turns them into characters using the
 * active layout. On a French AZERTY layout the digit row is punctuation, so
 * a scanner left on its US default sends `é"'(-è_çà&` for `2345678901`. The
 * till has to understand that rather than refuse every product.
 */
class ScanKeyboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_azerty_punctuation_is_read_back_as_digits(): void
    {
        // 2691659469348 through a French layout: 2=é 6=- 9=ç 1=& 5=( 4=' 3=" 8=_
        $this->assertSame('2691659469348', Barcode::normalizeScan('é-ç&-(ç\'-ç"\'_'));
        $this->assertSame('1234567890123', Barcode::normalizeScan('&é"\'(-è_çà&é"'));
    }

    public function test_real_codes_and_references_are_left_alone(): void
    {
        $this->assertSame('2691659469348', Barcode::normalizeScan('2691659469348'));
        $this->assertSame('ART-0001', Barcode::normalizeScan('ART-0001'), 'a dash in a reference is not a 6');
        $this->assertSame('ABC/123', Barcode::normalizeScan('ABC/123'));
        $this->assertSame('', Barcode::normalizeScan('   '));
    }

    public function test_a_scan_typed_through_an_azerty_layout_still_adds_the_article(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $article = Article::create([
            'reference' => 'ART-0001',
            'code_barre' => '2691659469348',
            'designation' => 'hp laptop',
            'prix_vente' => 234,
            'stock' => 22,
            'actif' => true,
        ]);

        $this->assertSame($article->id, Article::findByScan('é-ç&-(ç\'-ç"\'_')?->id);

        $component = Livewire::test(PointDeVente::class)
            ->call('scanner', 'é-ç&-(ç\'-ç"\'_')
            ->assertSee('hp laptop');

        $this->assertCount(1, $component->get('panier'));
    }
}
