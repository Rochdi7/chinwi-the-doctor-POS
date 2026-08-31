<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

class Locales
{
    /** Locales the language switcher accepts, in menu order. */
    public const SUPPORTED = [
        'fr' => 'Français',
        'ar' => 'العربية',
        'ary' => 'الدارجة',
    ];

    /** Locales written right to left. */
    public const RTL = ['ar', 'ary'];

    /** Locales that need the Arabic PDF pipeline (mPDF) instead of DomPDF. */
    public const ARABIC_SCRIPT = ['ar', 'ary'];

    public static function supported(string $locale): bool
    {
        return array_key_exists($locale, self::SUPPORTED);
    }

    public static function isRtl(?string $locale = null): bool
    {
        return in_array($locale ?? App::getLocale(), self::RTL, true);
    }

    public static function isArabicScript(?string $locale = null): bool
    {
        return in_array($locale ?? App::getLocale(), self::ARABIC_SCRIPT, true);
    }
}
