<?php

namespace App\Filament\Pages;

use App\Models\Article;
use App\Support\Barcode;
use App\Support\ScanQueue;
use Filament\Pages\Page;

/**
 * Turn any device with a camera into the barcode scanner: the cashier's
 * phone, a tablet, a second computer.
 *
 * The device opens this page, points its camera at the product, and the code
 * is pushed to the till open elsewhere (App\Filament\Pages\PointDeVente),
 * which adds it to the cart. No app to install: the decoding is done in the
 * browser by the BarcodeDetector API, with a ZXing fallback for iPhones,
 * which have none.
 *
 * Which till receives the scans is decided by the `till` code in the URL —
 * the till shows it as a QR code — and otherwise by the account in use, so
 * one person with one account needs no pairing at all.
 */
class ScannerTelephone extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'scanner';

    protected static string $view = 'filament.pages.scanner-telephone';

    /** The till this device feeds. */
    public string $till = '';

    public function mount(?string $till = null): void
    {
        $till = (string) ($till ?? request()->query('till', ''));

        $this->till = ScanQueue::isValid($till)
            ? $till
            : ScanQueue::tillFor(auth()->id());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.vente');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.scanner.label');
    }

    public function getTitle(): string
    {
        return __('app.scanner.label');
    }

    /**
     * The device holds this page in one hand at the counter, so it is kept
     * narrow rather than spread across a desktop-width panel.
     */
    public function getMaxContentWidth(): ?string
    {
        return 'lg';
    }

    /**
     * A code read by the camera. Answering with the article's name is what
     * makes the scanner device usable on its own: the cashier sees what was
     * recognised without looking at the till.
     *
     * @return array{ok: bool, message: string}
     */
    public function envoyer(string $code): array
    {
        $code = Barcode::normalizeScan($code);

        if ($code === '') {
            return ['ok' => false, 'message' => __('app.scanner.rien')];
        }

        $article = Article::findByScan($code);

        if (! $article) {
            return ['ok' => false, 'message' => __('app.scan.introuvable', ['code' => $code])];
        }

        ScanQueue::push($this->till, $code);

        return ['ok' => true, 'message' => $article->designation];
    }
}
