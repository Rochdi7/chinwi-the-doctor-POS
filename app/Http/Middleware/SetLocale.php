<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Locales;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale', config('app.locale'));
        if (! Locales::supported($locale)) {
            $locale = 'fr';
        }

        App::setLocale($locale);
        // Darija has its own app.php only: anything else (validation, Filament) reads Arabic, never English.
        app('translator')->setFallback($locale === 'ary' ? 'ar' : 'fr');
        // Carbon and ICU have no 'ary'; without this, translated dates would keep the boot locale.
        Carbon::setLocale($locale === 'ary' ? 'ar_MA' : $locale);

        return $next($request);
    }
}
