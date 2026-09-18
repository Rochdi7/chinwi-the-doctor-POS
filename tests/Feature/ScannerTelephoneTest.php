<?php

namespace Tests\Feature;

use App\Filament\Pages\PointDeVente;
use App\Filament\Pages\ScannerTelephone;
use App\Models\Article;
use App\Models\User;
use App\Support\ScanQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The phone as the scanner: a code read by its camera has to reach the till
 * open on the PC and land in the cart, without the two pages talking to
 * each other directly.
 */
class ScannerTelephoneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]);

        $this->actingAs($this->user);
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

    public function test_the_scanner_page_opens(): void
    {
        $this->get('/admin/scanner')
            ->assertOk()
            ->assertSee(__('app.scanner.titre'));
    }

    public function test_a_code_read_by_the_phone_is_queued_and_named_back(): void
    {
        $article = $this->article(['designation' => 'Huile 5L']);

        // The page answers with the article so the phone shows what it read.
        $answer = Livewire::test(ScannerTelephone::class)->instance()->envoyer($article->code_barre);

        $this->assertTrue($answer['ok']);
        $this->assertSame('Huile 5L', $answer['message']);
        $this->assertSame([$article->code_barre], ScanQueue::drain($this->user->id));
    }

    public function test_an_unknown_code_is_refused_and_never_queued(): void
    {
        $answer = Livewire::test(ScannerTelephone::class)->instance()->envoyer('9999999999999');

        $this->assertFalse($answer['ok']);
        $this->assertSame([], ScanQueue::drain($this->user->id));
    }

    public function test_the_till_picks_up_what_the_phone_scanned(): void
    {
        $lait = $this->article(['designation' => 'Lait 1L']);
        $pain = $this->article(['designation' => 'Pain', 'prix_vente' => 2]);

        $till = Livewire::test(PointDeVente::class);
        $this->assertSame([], $till->get('panier'), 'cart starts empty');

        // The phone scans two products.
        $phone = Livewire::test(ScannerTelephone::class)->instance();
        $phone->envoyer($lait->code_barre);
        $phone->envoyer($lait->code_barre);
        $phone->envoyer($pain->code_barre);

        $till->call('recupererScans')->assertSee('Lait 1L')->assertSee('Pain');

        $lines = array_values($till->get('panier'));

        $this->assertCount(2, $lines, 'the same article stays on one line');
        $this->assertEquals(2, $lines[0]['quantite']);
        $this->assertEquals(1, $lines[1]['quantite']);
    }

    public function test_draining_twice_never_adds_the_same_scan_twice(): void
    {
        $article = $this->article();

        Livewire::test(ScannerTelephone::class)->instance()->envoyer($article->code_barre);

        $till = Livewire::test(PointDeVente::class)
            ->call('recupererScans')
            ->call('recupererScans');

        $lines = array_values($till->get('panier'));

        $this->assertCount(1, $lines);
        $this->assertEquals(1, $lines[0]['quantite']);
    }

    public function test_an_empty_queue_leaves_the_cart_alone(): void
    {
        $till = Livewire::test(PointDeVente::class)->call('recupererScans');

        $this->assertSame([], $till->get('panier'));
    }

    public function test_each_cashier_has_their_own_queue(): void
    {
        $article = $this->article();
        $other = User::create([
            'name' => 'Caissier',
            'email' => 'caissier@local.test',
            'password' => bcrypt('admin1234'),
        ]);

        ScanQueue::push($other->id, $article->code_barre);

        // This till must not see the other cashier's scan.
        $till = Livewire::test(PointDeVente::class)->call('recupererScans');

        $this->assertSame([], $till->get('panier'));
        $this->assertSame([$article->code_barre], ScanQueue::drain($other->id));
    }

    public function test_the_till_polls_for_phone_scans(): void
    {
        Livewire::test(PointDeVente::class)
            ->assertSeeHtml('$wire.recupererScans()');
    }
}
