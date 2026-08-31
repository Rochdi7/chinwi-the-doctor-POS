<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));

        $this->article = Article::create([
            'reference' => 'STK-1',
            'designation' => 'Article stock',
            'prix_vente' => 100,
            'stock' => 50,
            'tva' => 20,
        ]);
    }

    private function newItem(float $qty = 2): InvoiceItem
    {
        $client = Client::create(['raison_sociale' => 'STOCK CLIENT']);

        $invoice = Invoice::create([
            'numero' => Invoice::nextNumero(),
            'client_id' => $client->id,
            'date_facture' => now(),
            'statut' => 'validee',
        ]);

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'article_id' => $this->article->id,
            'designation' => $this->article->designation,
            'quantite' => $qty,
            'prix_unitaire' => 100,
            'tva' => 20,
        ]);
    }

    private function stock(): float
    {
        return (float) $this->article->fresh()->stock;
    }

    public function test_selling_decrements_stock_once(): void
    {
        $this->newItem(2);

        $this->assertSame(48.0, $this->stock());
    }

    public function test_editing_price_or_label_never_moves_stock(): void
    {
        $item = $this->newItem(2);
        $this->assertSame(48.0, $this->stock());

        // The repeater re-saves every line on each invoice save. Only a real
        // quantity change may touch the warehouse.
        $item->update(['prix_unitaire' => 250]);
        $this->assertSame(48.0, $this->stock(), 'price edit left stock alone');

        $item->update(['designation' => 'Nouveau libellé']);
        $this->assertSame(48.0, $this->stock(), 'label edit left stock alone');

        $item->update(['remise' => 30]);
        $this->assertSame(48.0, $this->stock(), 'discount edit left stock alone');

        $item->save();
        $item->save();
        $this->assertSame(48.0, $this->stock(), 'repeated saves left stock alone');
    }

    public function test_quantity_change_applies_only_the_delta(): void
    {
        $item = $this->newItem(2);

        $item->update(['quantite' => 5]);
        $this->assertSame(45.0, $this->stock(), '3 more units left the shelf');

        $item->update(['quantite' => 1]);
        $this->assertSame(49.0, $this->stock(), '4 units returned');
    }

    public function test_deleting_a_line_returns_the_stock(): void
    {
        $item = $this->newItem(4);
        $this->assertSame(46.0, $this->stock());

        $item->delete();
        $this->assertSame(50.0, $this->stock(), 'stock fully restored');
    }

    public function test_switching_article_returns_stock_to_the_original(): void
    {
        $other = Article::create([
            'reference' => 'STK-2',
            'designation' => 'Autre article',
            'prix_vente' => 80,
            'stock' => 20,
            'tva' => 20,
        ]);

        $item = $this->newItem(3);
        $this->assertSame(47.0, $this->stock());

        $item->update(['article_id' => $other->id]);

        $this->assertSame(50.0, $this->stock(), 'first article made whole');
        $this->assertSame(17.0, (float) $other->fresh()->stock, 'second article charged');
    }
}
