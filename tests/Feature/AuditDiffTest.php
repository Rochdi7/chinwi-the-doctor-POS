<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\User;
use App\Support\AuditDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDiffTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(User::create([
            'name' => 'Patron',
            'email' => 'patron@local.test',
            'password' => bcrypt('secret1234'),
        ]));
    }

    public function test_caisse_movement_shows_solde_before_and_after(): void
    {
        $this->login();

        $log = ActivityLog::record(
            'caisse.sortie',
            null,
            'Achat sacs',
            200,
            ['solde_avant' => 500, 'solde_apres' => 300],
        );
        $rows = AuditDiff::rows($log);

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('500,00', $rows[0]['avant']);
        $this->assertStringContainsString('300,00', $rows[0]['apres']);
        $this->assertStringStartsWith('−', $rows[0]['delta']);
    }

    public function test_update_shows_old_and_new_values_with_delta(): void
    {
        $this->login();

        $article = Article::create([
            'reference' => 'A1',
            'designation' => 'Produit A',
            'prix_vente' => 120,
            'stock' => 10,
            'tva' => 20,
        ]);

        $article->update(['prix_vente' => 99]);

        $log = ActivityLog::where('event', 'updated')
            ->where('subject_type', 'Article')
            ->latest('id')
            ->firstOrFail();

        $prix = collect(AuditDiff::rows($log))
            ->firstWhere('champ', __('app.article.prix_vente'));

        $this->assertNotNull($prix);
        $this->assertStringContainsString('120,00', $prix['avant']);
        $this->assertStringContainsString('99,00', $prix['apres']);
        $this->assertStringContainsString('21,00', $prix['delta']);
        $this->assertStringContainsString('→', AuditDiff::summary($log));
    }

    public function test_creation_shows_dash_before_and_value_after(): void
    {
        $this->login();

        Article::create([
            'reference' => 'B2',
            'designation' => 'Produit B',
            'prix_vente' => 50,
            'stock' => 4,
            'tva' => 20,
        ]);

        $log = ActivityLog::where('event', 'created')
            ->where('subject_type', 'Article')
            ->firstOrFail();

        $rows = collect(AuditDiff::rows($log));
        $designation = $rows->firstWhere('champ', __('app.article.designation'));

        $this->assertSame('—', $designation['avant']);
        $this->assertSame('Produit B', $designation['apres']);
        $this->assertFalse($rows->contains(fn ($r) => $r['champ'] === 'Id'));
    }
}
