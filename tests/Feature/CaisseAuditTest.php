<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaisseAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_payment_feeds_the_single_caisse_and_audit_trail(): void
    {
        $this->actingAs(User::create([
            'name' => 'Caissier',
            'email' => 'caissier@local.test',
            'password' => bcrypt('secret1234'),
        ]));

        $client = Client::create(['raison_sociale' => 'CLIENT A']);
        $article = Article::create([
            'reference' => 'A1',
            'designation' => 'Produit A',
            'prix_vente' => 100,
            'stock' => 10,
            'tva' => 20,
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
            'quantite' => 3,
            'prix_unitaire' => 100,
            'tva' => 20,
        ]);

        $this->assertEquals(360.00, $invoice->refresh()->total_ttc);
        $this->assertEquals(7, $article->refresh()->stock, 'stock decremented');

        Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'date_paiement' => now(),
            'montant' => 200,
            'mode' => 'especes',
        ]);

        $this->assertSame('partielle', $invoice->refresh()->statut);
        $this->assertEquals(160.00, $client->refresh()->solde);
        $this->assertEquals(200.00, Caisse::solde(), 'cash landed in the shared drawer');

        $tpe = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'date_paiement' => now(),
            'montant' => 160,
            'mode' => 'tpe',
        ]);

        $this->assertSame('payee', $invoice->refresh()->statut);
        $this->assertEquals(200.00, Caisse::solde(), 'TPE does not touch the cash drawer');

        $tpe->delete();
        $this->assertSame('partielle', $invoice->refresh()->statut);
        $this->assertEquals(200.00, Caisse::solde());
    }

    public function test_every_log_line_carries_millisecond_precision_and_a_user(): void
    {
        $user = User::create([
            'name' => 'Caissier',
            'email' => 'caissier2@local.test',
            'password' => bcrypt('secret1234'),
        ]);
        $this->actingAs($user);

        Client::create(['raison_sociale' => 'CLIENT B']);
        Caisse::mouvement('entree', 50, 'Fond de caisse');

        $logs = ActivityLog::all();
        $this->assertGreaterThanOrEqual(2, $logs->count());

        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->user_id);
            $this->assertNotNull($log->occurred_at);
        }

        $raw = \DB::table('activity_logs')->orderByDesc('id')->value('occurred_at');
        $this->assertMatchesRegularExpression('/\.\d{3}$/', $raw, 'timestamp stored with milliseconds');

        $mvt = ActivityLog::where('event', 'caisse.entree')->firstOrFail();
        $this->assertEquals(50.00, $mvt->montant);
    }
}
