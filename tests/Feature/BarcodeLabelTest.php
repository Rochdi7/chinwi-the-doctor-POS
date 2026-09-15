<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Models\User;
use App\Support\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A label that decodes in a test but has no quiet zone, or a code that was
 * typed instead of scanned, is why "the scanner reads everything but my
 * products". These pin down what makes a label readable at the till.
 */
class BarcodeLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_label_has_white_quiet_zones_either_side_of_the_bars(): void
    {
        $image = imagecreatefromstring(Barcode::png(Barcode::generate(), 3, 60));
        $width = imagesx($image);
        $y = (int) (imagesy($image) / 2);

        // GS1 wants 11 modules on the left; at widthFactor 3 that is 33 px.
        foreach (range(0, 32) as $x) {
            $this->assertTrue($this->isWhite($image, $x, $y), "ink in the left quiet zone at x={$x}");
            $this->assertTrue($this->isWhite($image, $width - 1 - $x, $y), "ink in the right quiet zone at x=".($width - 1 - $x));
        }

        // And there really are bars in between.
        $ink = 0;
        for ($x = 0; $x < $width; $x++) {
            $ink += $this->isWhite($image, $x, $y) ? 0 : 1;
        }
        $this->assertGreaterThan(40, $ink, 'no bars on the scan line');
    }

    public function test_the_label_prints_the_digits_under_the_bars(): void
    {
        $code = Barcode::generate();
        $image = imagecreatefromstring(Barcode::png($code, 3, 60));
        $width = imagesx($image);
        $height = imagesy($image);

        // Taller than the bars alone: something sits underneath.
        $this->assertGreaterThan(60 + 8, $height);

        // Ink in the band below the bars: the human-readable code.
        $ink = 0;
        for ($y = 60 + 8; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $ink += $this->isWhite($image, $x, $y) ? 0 : 1;
            }
        }
        $this->assertGreaterThan(50, $ink, 'nothing printed under the bars');
    }

    public function test_the_background_is_opaque_white_not_transparent(): void
    {
        // A transparent PNG printed by some drivers comes out black.
        $image = imagecreatefromstring(Barcode::png(Barcode::generate()));

        $this->assertTrue($this->isWhite($image, 0, 0));
        $this->assertTrue($this->isWhite($image, imagesx($image) - 1, imagesy($image) - 1));
    }

    public function test_standard_retail_codes_are_recognised(): void
    {
        foreach (['12345670', '012345678905', '5901234123457', '15901234123456'] as $ok) {
            $this->assertTrue(Barcode::isStandardRetail($ok), $ok);
        }

        foreach (['ddddddddddddddd', 'ART-0001', '12345', '59012341234', '5901234123457x', ''] as $bad) {
            $this->assertFalse(Barcode::isStandardRetail($bad), $bad);
        }
    }

    public function test_a_mistyped_ean13_is_refused_on_the_article_form(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'designation' => 'Lait 1L',
                'reference' => 'ART-0001',
                'prix_vente' => 10,
                // Real code is 5901234123457; last digit changed = a typo.
                'code_barre' => '5901234123458',
            ])
            ->call('create')
            ->assertHasFormErrors(['code_barre']);
    }

    public function test_a_real_manufacturer_code_is_accepted_on_the_article_form(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'designation' => 'Lait 1L',
                'reference' => 'ART-0001',
                'prix_vente' => 10,
                'code_barre' => '5901234123457',
            ])
            ->call('create')
            ->assertHasNoFormErrors(['code_barre']);
    }

    private function isWhite(\GdImage $image, int $x, int $y): bool
    {
        $rgb = imagecolorat($image, $x, $y);

        return (($rgb >> 16) & 0xFF) > 200 && (($rgb >> 8) & 0xFF) > 200 && ($rgb & 0xFF) > 200;
    }
}
