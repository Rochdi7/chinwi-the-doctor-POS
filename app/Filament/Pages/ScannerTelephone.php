<?php

namespace App\Filament\Pages;

use App\Models\Article;
use App\Support\Barcode;
use App\Support\ScanQueue;
use Filament\Pages\Page;

/**
 * Turn the cashier's phone into the barcode scanner.
 *
 * The phone opens this page, points its camera at the product, and the code
 * is pushed to the till open on the PC (App\Filament\Pages\PointDeVente),
 * which adds it to the cart. No app to install: the decoding is done in the
 * browser by the BarcodeDetector API, with a ZXing fallback for iPhones on
 * older iOS.
 */
class ScannerTelephone extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'scanner';

    protected static string $view = 'filament.pages.scanner-telephone';

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
     * The phone holds this page in one hand at the counter: the sidebar,
     * open by default on a narrow screen, would cover the camera.
     */
    public function getMaxContentWidth(): ?string
    {
        return 'lg';
    }

    /**
     * A code read by the phone camera. Answering with the article's name is
     * what makes the phone usable on its own: the cashier sees what was
     * recognised without looking at the PC.
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

        ScanQueue::push(auth()->id(), $code);

        return ['ok' => true, 'message' => $article->designation];
    }
}
