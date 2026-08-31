<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@local.test'],
            ['name' => 'Admin', 'password' => bcrypt('admin1234')],
        );
    }

    public function test_panel_pages_load(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            '/admin',
            '/admin/clients',
            '/admin/articles',
            '/admin/invoices',
            '/admin/caisse-mouvements',
            '/admin/activity-logs',
            '/admin/parametres',
        ] as $url) {
            $this->assertSame(200, $this->get($url)->getStatusCode(), $url);
        }
    }

    public function test_invoice_pdf_streams(): void
    {
        $this->actingAs($this->admin());

        $client = Client::create(['raison_sociale' => 'PDF TEST']);
        $article = Article::create([
            'reference' => 'PDF-'.uniqid(),
            'designation' => 'Article PDF',
            'prix_vente' => 50,
            'stock' => 5,
        ]);
        $invoice = Invoice::create([
            'numero' => Invoice::nextNumero(),
            'client_id' => $client->id,
            'date_facture' => now(),
            'statut' => 'validee',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'article_id' => $article->id,
            'designation' => $article->designation,
            'quantite' => 2,
            'prix_unitaire' => 50,
            'tva' => 20,
        ]);

        $response = $this->get(route('invoice.pdf', $invoice));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $invoice->items()->delete();
        $invoice->delete();
        $client->delete();
        $article->delete();
    }
}
