<?php

namespace Tests\Feature;

use App\Filament\Pages\PointDeVente;
use App\Models\User;
use App\Support\ScannerUsb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The till tells the cashier whether Windows sees the USB scanner. The probe
 * itself is PowerShell and stays out of the tests: what is checked here is
 * how its answer file is read, and what the till shows for each answer.
 */
class ScannerUsbTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Never the real storage: a till open on this machine writes there.
        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scanner-usb-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.DIRECTORY_SEPARATOR.'app');
        $this->app->useStoragePath($this->storage);

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    private function windowsAnswers(string $json, ?int $age = null): void
    {
        $file = $this->storage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'scanner-usb.json';
        file_put_contents($file, $json);

        if ($age !== null) {
            touch($file, time() - $age);
        }

        clearstatcache();
    }

    public function test_it_is_unknown_until_windows_has_answered(): void
    {
        $this->assertSame('unknown', ScannerUsb::status()['state']);
    }

    public function test_it_reads_each_answer(): void
    {
        $this->windowsAnswers('{"present":false}');
        $this->assertSame('absent', ScannerUsb::status()['state']);

        $this->windowsAnswers('{"present":true,"ok":true,"serie":false,"name":""}');
        $this->assertSame(['state' => 'connected', 'name' => null], ScannerUsb::status());

        $this->windowsAnswers('{"present":true,"ok":true,"serie":true,"name":"Honeywell 7580g (COM3)"}');
        $this->assertSame(['state' => 'serie', 'name' => 'Honeywell 7580g (COM3)'], ScannerUsb::status());

        $this->windowsAnswers('{"present":true,"ok":false,"serie":false,"name":""}');
        $this->assertSame('erreur', ScannerUsb::status()['state']);
    }

    public function test_a_half_written_or_stale_answer_is_unknown_not_absent(): void
    {
        $this->windowsAnswers('{"pres');
        $this->assertSame('unknown', ScannerUsb::status()['state']);

        $this->windowsAnswers('{"present":true,"ok":true,"serie":false,"name":""}', age: 3600);
        $this->assertSame('unknown', ScannerUsb::status()['state']);
    }

    public function test_the_till_shows_the_scanner_state(): void
    {
        $this->windowsAnswers('{"present":true,"ok":true,"serie":false,"name":""}');
        Livewire::test(PointDeVente::class)
            ->assertSee(__('app.pos.usb.connected'))
            ->assertDontSee(__('app.pos.usb.absent'));

        // Unplugged while the till is open: the poll's re-render picks it up.
        $till = Livewire::test(PointDeVente::class);
        $this->windowsAnswers('{"present":false}');
        $till->call('recupererScans')->assertSee(__('app.pos.usb.absent'));
    }

    public function test_the_till_says_nothing_when_windows_cannot_be_asked(): void
    {
        Livewire::test(PointDeVente::class)
            ->assertDontSee(__('app.pos.usb.connected'))
            ->assertDontSee(__('app.pos.usb.absent'));
    }
}
