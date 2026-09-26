<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\BackOfficeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\ArticleLabelController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

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
        Route::get('/journee', [PosController::class, 'journee'])->name('journee');
        Route::get('/articles', [PosController::class, 'articles'])->name('articles');
        Route::post('/scan', [PosController::class, 'scan'])->name('scan');
        Route::get('/scans', [PosController::class, 'scans'])->name('scans');
        Route::post('/apercu', [PosController::class, 'apercu'])->name('apercu');
        Route::post('/ventes', [PosController::class, 'vente'])->name('vente');
    });

    // Back office (the screens the Filament panel used to provide).
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [BackOfficeController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/{key}', [BackOfficeController::class, 'dashboardDetail'])->name('dashboard.detail');

        Route::get('/articles/nouveau', [ArticleController::class, 'nouveau'])->name('articles.nouveau');
        Route::post('/articles/code-barre', [ArticleController::class, 'codeBarre'])->name('articles.code-barre');
        Route::get('/articles/lookup', [ArticleController::class, 'lookup'])->name('articles.lookup');
        Route::post('/articles/{article}/image', [ArticleController::class, 'image'])->name('articles.image');
        Route::delete('/articles/{article}/image', [ArticleController::class, 'destroyImage'])->name('articles.image.destroy');
        Route::apiResource('articles', ArticleController::class);
        Route::apiResource('categories', CategoryController::class)->except('show');

        Route::get('/clients/options', [ClientController::class, 'options'])->name('clients.options');
        Route::apiResource('clients', ClientController::class);

        Route::get('/ventes/nouveau', [InvoiceController::class, 'nouveau'])->name('ventes.nouveau');
        Route::post('/ventes/{invoice}/encaisser', [InvoiceController::class, 'encaisser'])->name('ventes.encaisser');
        Route::apiResource('ventes', InvoiceController::class)->parameters(['ventes' => 'invoice']);

        Route::apiResource('reglements', PaymentController::class)->only(['index', 'show', 'update', 'destroy'])->parameters(['reglements' => 'payment']);

        Route::get('/alertes', [BackOfficeController::class, 'alertes'])->name('alertes');
        Route::get('/caisse', [BackOfficeController::class, 'caisse'])->name('caisse');
        Route::get('/journal', [BackOfficeController::class, 'journal'])->name('journal');
        Route::get('/journal/evenements', [BackOfficeController::class, 'journalEvents'])->name('journal.events');
        Route::get('/parametres', [BackOfficeController::class, 'settings'])->name('parametres');
        Route::put('/parametres', [BackOfficeController::class, 'saveSettings'])->name('parametres.save');

        Route::get('/scanner/till', [BackOfficeController::class, 'scannerTill'])->name('scanner.till');
        Route::post('/scanner/envoyer', [BackOfficeController::class, 'scannerEnvoyer'])->name('scanner.envoyer');
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
