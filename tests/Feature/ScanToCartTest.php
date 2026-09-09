<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Article;
use App\Models\User;
use App\Support\ScanCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The till has to work with both hands full: a USB scanner types the barcode
 * and presses Enter, and the line must land in the sale without a click.
 */
class ScanToCartTest extends TestCase
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
            'prix_vente' => 12,
            'stock' => 40,
            'tva' => 20,
            'actif' => true,
        ], $attributes));
    }

    public function test_scanning_a_barcode_puts_the_article_in_the_sale(): void
    {
        $article = $this->article(['designation' => 'Lait 1L', 'prix_vente' => 12, 'tva' => 20]);

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->assertHasNoFormErrors()
            ->instance()
            ->form
            ->getRawState()['items'];

        $lines = array_values($items);

        $this->assertCount(1, $lines, 'the scan added exactly one line');
        $this->assertSame($article->id, (int) $lines[0]['article_id']);
        $this->assertSame('Lait 1L', $lines[0]['designation']);
        $this->assertEquals(1, $lines[0]['quantite']);
        $this->assertEquals(12, $lines[0]['prix_unitaire']);
        $this->assertEquals(20, $lines[0]['tva']);
    }

    public function test_the_scan_box_empties_itself_for_the_next_article(): void
    {
        // A code left behind would be prepended to whatever is scanned next.
        $article = $this->article();

        Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->assertFormSet(['scan' => '']);
    }

    public function test_scanning_the_same_article_twice_raises_the_quantity(): void
    {
        // Three identical tins are one line of 3, not three lines of 1.
        $article = $this->article();

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->fillForm(['scan' => $article->code_barre])
            ->fillForm(['scan' => $article->code_barre])
            ->instance()
            ->form
            ->getRawState()['items'];

        $lines = array_values($items);

        $this->assertCount(1, $lines, 'the repeat scans stayed on one line');
        $this->assertEquals(3, $lines[0]['quantite']);
    }

    public function test_two_different_articles_get_their_own_lines(): void
    {
        $lait = $this->article(['designation' => 'Lait 1L']);
        $pain = $this->article(['designation' => 'Pain complet']);

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $lait->code_barre])
            ->fillForm(['scan' => $pain->code_barre])
            ->instance()
            ->form
            ->getRawState()['items'];

        $designations = array_column(array_values($items), 'designation');

        $this->assertCount(2, $designations);
        $this->assertContains('Lait 1L', $designations);
        $this->assertContains('Pain complet', $designations);
    }

    public function test_a_reference_typed_by_hand_still_works(): void
    {
        // The till has to keep selling when a label is torn or missing.
        $article = $this->article(['reference' => 'ART-MANUEL']);

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => 'ART-MANUEL'])
            ->instance()
            ->form
            ->getRawState()['items'];

        $this->assertSame($article->id, (int) array_values($items)[0]['article_id']);
    }

    public function test_an_unknown_code_adds_nothing(): void
    {
        $this->article();

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => '0000000000000'])
            ->instance()
            ->form
            ->getRawState()['items'];

        $this->assertSame([], ScanCart::withoutBlankRows((array) $items));
    }

    public function test_an_inactive_article_cannot_be_scanned_into_a_sale(): void
    {
        $article = $this->article(['designation' => 'Produit retiré', 'actif' => false]);

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->instance()
            ->form
            ->getRawState()['items'];

        $this->assertSame([], ScanCart::withoutBlankRows((array) $items));
    }

    public function test_an_article_out_of_stock_is_still_sold(): void
    {
        // Loose weight and un-keyed deliveries go negative legitimately;
        // refusing the sale would stop the counter.
        $article = $this->article(['stock' => 0]);

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->instance()
            ->form
            ->getRawState()['items'];

        $this->assertCount(1, ScanCart::withoutBlankRows((array) $items));
    }

    public function test_the_scan_does_not_leave_the_starting_blank_row_behind(): void
    {
        // The form opens with one empty repeater row; the first scan must
        // replace it rather than sit underneath it.
        $article = $this->article();

        $items = Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->instance()
            ->form
            ->getRawState()['items'];

        $this->assertCount(1, $items, 'no empty row was left above the scan');
    }

    public function test_a_barcode_wins_over_another_articles_reference(): void
    {
        // Nothing stops one article's reference from being another's barcode.
        // The scanner always sends a barcode, so that is what must match.
        $collision = '2123456789012';

        $byReference = $this->article(['designation' => 'Par référence', 'reference' => $collision]);
        $byBarcode = $this->article(['designation' => 'Par code-barre', 'code_barre' => $collision]);

        $found = Article::findByScan($collision);

        $this->assertSame($byBarcode->id, $found->id);
        $this->assertNotSame($byReference->id, $found->id);
    }

    /** The till is used in all three languages. */
    #[DataProvider('locales')]
    public function test_the_scan_field_is_labelled_in_every_language(string $locale): void
    {
        $this->article();
        session(['locale' => $locale]);
        app()->setLocale($locale);

        $html = $this->get('/admin/invoices/create')->assertOk()->getContent();

        foreach (['app.scan.label', 'app.scan.aide'] as $key) {
            $label = __($key);

            $this->assertNotSame($key, $label, "{$key} is translated in {$locale}");
            $this->assertStringContainsString(
                htmlspecialchars($label, ENT_QUOTES),
                $html,
                "{$key} shown in {$locale}"
            );
        }
    }

    public static function locales(): array
    {
        return ['french' => ['fr'], 'arabic' => ['ar'], 'darija' => ['ary']];
    }
}
