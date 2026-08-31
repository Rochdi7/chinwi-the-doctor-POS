<?php

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

Route::middleware('auth')->group(function () {
    Route::get('/facture/{invoice}/pdf', InvoicePdfController::class)->name('invoice.pdf');
    Route::get('/reglement/{payment}/pdf', PaymentReceiptController::class)->name('payment.pdf');
    Route::get('/article/{article}/code-barre', BarcodeController::class)->name('article.barcode');
});
