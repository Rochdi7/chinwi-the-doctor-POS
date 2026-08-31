<?php

namespace App\Support;

use App\Models\Setting;

class Money
{
    /**
     * Money always renders in Latin digits with the configured currency suffix.
     *
     * Filament's money() defers to Intl, which under the `ar` locale emits
     * Arabic-Indic digits (٢٨٨٠) and the د.م. symbol. Shop staff read prices
     * off invoices and the drawer in Latin digits, so amounts stay stable
     * across both languages while labels around them translate.
     */
    public static function format(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' '.self::devise();
    }

    public static function devise(): string
    {
        return Setting::get('devise', 'DH');
    }
}
