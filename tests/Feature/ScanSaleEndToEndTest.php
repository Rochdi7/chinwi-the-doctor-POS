<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Article;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Scanning is only finished when the sale it produced is saved: the line has
 * to persist, the totals have to add up and the stock has to come down.
 */
class ScanSaleEndToEndTest extends TestCase
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
            'prix_vente' => 10,
            'stock' => 100,
            'tva' => 20,
            'actif' => true,
        ], $attributes));
    }

    public function test_a_scanned_sale_saves_and_moves_the_stock(): void
    {
        $article = $this->article(['prix_vente' => 10, 'stock' => 100, 'tva' => 20]);

        Livewire::test(CreateInvoice::class)
            ->fillForm(['scan' => $article->code_barre])
            ->fillForm(['scan' => $article->code_barre])
            ->fillForm([
                'date_facture' => now()->toDateString(),
                'encaisser_maintenant' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::latest('id')->firstOrFail();
        $line = $invoice->items()->firstOrFail();

        $this->assertSame($article->id, (int) $line->article_id);
        $this->assertEquals(2, $line->quantite, 'both scans landed on one line');
        $this->assertEquals(20, $line->total_ht);
        $this->assertEquals(24, $line->total_ttc);

        // 2 sold out of 100.
        $this->assertEquals(98, $article->fresh()->stock);
    }
}
