<?php

use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\ArticleLabelController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/langue/{locale}', function (string $locale) {
    if (Locales::supported($locale)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('langue');

/*
| JSON for the React app (resources/react). These are ordinary web routes:
| same session cookie, same CSRF check, same `auth` guard as the Filament
| panel, so the React app needs no token and no second login system.
*/
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/session', [SessionController::class, 'show'])->name('session');
    Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:20,1')->name('login');
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
    Route::post('/locale', [SessionController::class, 'locale'])->name('locale');

    Route::middleware('auth')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/init', [PosController::class, 'init'])->name('init');
        Route::get('/articles', [PosController::class, 'articles'])->name('articles');
        Route::post('/scan', [PosController::class, 'scan'])->name('scan');
        Route::get('/scans', [PosController::class, 'scans'])->name('scans');
        Route::post('/apercu', [PosController::class, 'apercu'])->name('apercu');
        Route::post('/ventes', [PosController::class, 'vente'])->name('vente');
    });
});

// The React app. Client-side routes (/app/login, /app/pos...) all land here.
Route::view('/app/{any?}', 'react')->where('any', '.*')->name('react');

Route::middleware('auth')->group(function () {
    Route::get('/facture/{invoice}/pdf', InvoicePdfController::class)->name('invoice.pdf');
    Route::get('/reglement/{payment}/pdf', PaymentReceiptController::class)->name('payment.pdf');
    Route::get('/article/{article}/code-barre', BarcodeController::class)->name('article.barcode');
    Route::get('/article/{article}/etiquette', ArticleLabelController::class)->name('article.label');
});
