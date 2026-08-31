<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Support\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Ean13Decoder;
use Tests\TestCase;

/**
 * A barcode is only worth printing if a device can read it back, so these
 * tests decode the rendered pixels rather than trusting the generator.
 */
class BarcodeScannableTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_decoder_itself_reads_a_real_world_barcode(): void
    {
        // Guards the other tests: if this fails, the decoder is wrong,
        // not the generator.
        $known = '5901234123457';

        $this->assertSame($known, Ean13Decoder::decode(Barcode::png($known)));
    }

    public function test_every_generated_code_decodes_back_to_itself(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $code = Barcode::generate();

            $this->assertSame(
                $code,
                Ean13Decoder::decode(Barcode::png($code)),
                "generated code {$code} did not scan back",
            );
        }
    }

    /** Small labels are where a barcode usually stops scanning. */
    #[DataProvider('labelSizes')]
    public function test_a_label_scans_at_the_sizes_the_app_prints(int $widthFactor, int $height): void
    {
        $code = Barcode::generate();

        $this->assertSame(
            $code,
            Ean13Decoder::decode(Barcode::png($code, $widthFactor, $height)),
            "unreadable at widthFactor {$widthFactor}",
        );
    }

    public static function labelSizes(): array
    {
        return [
            'smallest' => [1, 30],
            'form preview' => [3, 60],
            'download' => [3, 80],
            'large' => [4, 100],
        ];
    }

    public function test_the_downloaded_png_is_the_one_that_scans(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $article = Article::create([
            'reference' => 'ART-0001',
            'code_barre' => Barcode::generate(),
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 5,
        ]);

        $png = $this->get(route('article.barcode', $article))->assertOk()->getContent();

        $this->assertSame($article->code_barre, Ean13Decoder::decode($png));
    }

    public function test_the_form_preview_is_the_one_that_scans(): void
    {
        $code = Barcode::generate();

        $uri = Barcode::dataUri($code);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')));

        $this->assertSame($code, Ean13Decoder::decode($png));
    }

    public function test_a_code_with_a_broken_check_digit_is_never_generated(): void
    {
        // A scanner refuses an EAN-13 whose check digit does not add up,
        // so the last digit must always be computed, never random.
        for ($i = 0; $i < 20; $i++) {
            $code = Barcode::generate();

            $this->assertSame(
                Barcode::checksum(substr($code, 0, 12)),
                (int) $code[12],
                "check digit wrong on {$code}",
            );
        }
    }

    public function test_a_hand_typed_code_falls_back_to_code128_and_still_renders(): void
    {
        // Not an EAN-13: it must still produce a real barcode image rather
        // than throwing, because staff type manufacturer codes by hand.
        foreach (['ddddddddddddddd', 'ART-0001', 'ABC/123'] as $code) {
            $png = Barcode::png($code);

            $this->assertStringStartsWith("\x89PNG", $png, "{$code} did not render");
            $this->assertGreaterThan(100, strlen($png));
        }
    }
}
