<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Support\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BarcodeTest extends TestCase
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

    public function test_a_generated_code_is_a_valid_ean13(): void
    {
        $code = Barcode::generate();

        $this->assertMatchesRegularExpression('/^\d{13}$/', $code);
        $this->assertTrue(Barcode::isValidEan13($code));
    }

    public function test_generated_codes_use_the_in_store_prefix(): void
    {
        // Prefix 2 is the GS1 range reserved for internal use, so a generated
        // code can never collide with a real manufacturer barcode.
        $this->assertSame('2', Barcode::generate()[0]);
    }

    public function test_the_checksum_matches_a_known_real_barcode(): void
    {
        $this->assertTrue(Barcode::isValidEan13('5901234123457'));
        $this->assertFalse(Barcode::isValidEan13('5901234123450'), 'wrong check digit rejected');
        $this->assertFalse(Barcode::isValidEan13('590123412345'), 'too short');
    }

    public function test_generated_codes_do_not_repeat(): void
    {
        $codes = [];

        for ($i = 0; $i < 40; $i++) {
            $codes[] = Barcode::generate();
        }

        $this->assertCount(40, array_unique($codes));
    }

    public function test_generation_skips_a_code_already_in_use(): void
    {
        $taken = Barcode::generate();

        Article::create([
            'reference' => 'ART-TAKEN',
            'code_barre' => $taken,
            'designation' => 'Déjà pris',
            'prix_vente' => 1,
            'stock' => 1,
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->assertNotSame($taken, Barcode::generate());
        }
    }

    public function test_two_articles_cannot_share_a_barcode(): void
    {
        $code = Barcode::generate();

        Article::create([
            'reference' => 'ART-0001',
            'code_barre' => $code,
            'designation' => 'Premier',
            'prix_vente' => 1,
            'stock' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Article::create([
            'reference' => 'ART-0002',
            'code_barre' => $code,
            'designation' => 'Doublon',
            'prix_vente' => 1,
            'stock' => 1,
        ]);
    }

    public function test_a_barcode_can_be_edited_to_the_manufacturer_code(): void
    {
        $article = Article::create([
            'reference' => 'ART-0001',
            'code_barre' => Barcode::generate(),
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 5,
        ]);

        $article->update(['code_barre' => '5901234123457']);

        $this->assertSame('5901234123457', $article->fresh()->code_barre);
    }

    public function test_the_label_image_downloads_as_a_png(): void
    {
        $article = Article::create([
            'reference' => 'ART-0001',
            'code_barre' => Barcode::generate(),
            'designation' => 'Lait 1L',
            'prix_vente' => 10,
            'stock' => 5,
        ]);

        $response = $this->get(route('article.barcode', $article));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            $article->code_barre.'.png',
            $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_an_article_without_a_barcode_has_no_label_to_download(): void
    {
        $article = Article::create([
            'reference' => 'ART-0001',
            'designation' => 'Sans code',
            'prix_vente' => 10,
            'stock' => 5,
        ]);

        $this->get(route('article.barcode', $article))->assertNotFound();
    }

    public function test_a_hand_typed_code_still_renders_via_code128(): void
    {
        // Not an EAN-13, so the generator must fall back rather than fail.
        $png = Barcode::png('ABC-123/XYZ');

        $this->assertStringStartsWith("\x89PNG", $png);
    }
}
