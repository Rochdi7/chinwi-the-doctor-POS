<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Observers\AuditObserver;
use App\Observers\InvoiceItemObserver;
use App\Observers\PaymentObserver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Client::observe(AuditObserver::class);
        Article::observe(AuditObserver::class);
        Invoice::observe(AuditObserver::class);
        InvoiceItem::observe(InvoiceItemObserver::class);
        Payment::observe(PaymentObserver::class);

        // The till stylesheet, served static so it is fetched once and never
        // re-sent inside a Livewire response. mtime busts the browser cache.
        $pos = public_path('css/pos.css');
        FilamentAsset::register([
            Css::make('pos', asset('css/pos.css').'?v='.(is_file($pos) ? filemtime($pos) : 1)),
        ], package: 'app');
    }
}
