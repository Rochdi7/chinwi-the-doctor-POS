<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/sync: the change feed every open screen polls so that the same
 * account opened on two devices shows the same data without a reload.
 */
class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create(['name' => 'Gérant', 'email' => 'gerant@local.test', 'password' => bcrypt('x-secret-1')]));
    }

    private function article(): Article
    {
        return Article::create(['reference' => 'ART-'.uniqid(), 'designation' => 'Lait 1L', 'prix_vente' => 10, 'stock' => 100, 'tva' => 0, 'actif' => true]);
    }

    public function test_it_needs_a_login(): void
    {
        auth()->logout();

        $this->getJson('/api/sync')->assertUnauthorized();
    }

    public function test_the_first_call_starts_from_now_with_nothing_to_refresh(): void
    {
        $this->article();

        $this->getJson('/api/sync')
            ->assertOk()
            ->assertExactJson(['cursor' => ActivityLog::max('id'), 'changes' => []]);
    }

    public function test_a_change_made_on_another_device_is_reported_once_then_forgotten(): void
    {
        $cursor = $this->getJson('/api/sync')->json('cursor');

        // "Another device": the same account writes through the API.
        $this->article();
        $this->putJson('/api/parametres', ['societe_nom' => 'Chinwi'])->assertOk();

        $poll = $this->getJson('/api/sync?since='.$cursor)->assertOk();

        $this->assertEqualsCanonicalizing(['Article', 'settings'], $poll->json('changes'));
        $this->assertGreaterThan($cursor, $poll->json('cursor'));
        $this->assertSame(ActivityLog::max('id'), $poll->json('cursor'));

        $this->getJson('/api/sync?since='.$poll->json('cursor'))
            ->assertOk()
            ->assertExactJson(['cursor' => $poll->json('cursor'), 'changes' => []]);
    }

    public function test_categories_are_part_of_the_feed(): void
    {
        $cursor = $this->getJson('/api/sync')->json('cursor');

        Category::create(['nom' => 'Boissons']);

        $this->assertSame(['Category'], $this->getJson('/api/sync?since='.$cursor)->json('changes'));
    }

    public function test_a_cursor_ahead_of_the_log_asks_for_a_full_refresh(): void
    {
        $this->article();

        $this->getJson('/api/sync?since=999999')
            ->assertOk()
            ->assertExactJson(['cursor' => ActivityLog::max('id'), 'changes' => ['*']]);
    }

    public function test_a_printed_pdf_only_touches_the_journal(): void
    {
        $cursor = $this->getJson('/api/sync')->json('cursor');

        ActivityLog::record('invoice.printed', $this->article(), 'PDF');
        $cursorAfterArticle = ActivityLog::query()->where('event', 'created')->max('id');

        $this->assertSame(['print'], $this->getJson('/api/sync?since='.$cursorAfterArticle)->json('changes'));
        $this->assertEqualsCanonicalizing(['Article', 'print'], $this->getJson('/api/sync?since='.$cursor)->json('changes'));
    }
}
