<?php
namespace Tests\Feature;
use App\Models\{ActivityLog, Article, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ActivityLogPageTest extends TestCase {
 use RefreshDatabase;
 public function test_activity_log_page_shows_before_after(): void {
  $u = User::create(['name'=>'Admin','email'=>'a@l.test','password'=>bcrypt('secret1234')]);
  $this->actingAs($u);
  $a = Article::create(['reference'=>'A1','designation'=>'Produit A','prix_vente'=>120,'stock'=>10,'tva'=>20]);
  $a->update(['prix_vente'=>99]);
  ActivityLog::record('caisse.sortie', null, 'Achat sacs', 200, ['solde_avant'=>500,'solde_apres'=>300]);
  $res = $this->get('/admin/activity-logs');
  $res->assertOk();
  $res->assertSee('Avant', false);
  $res->assertSee('120,00', false);
  $res->assertSee('99,00', false);
  $res->assertSee('500,00', false);
  $res->assertSee('300,00', false);
 }
}
