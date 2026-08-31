<?php

namespace App\Support;

class Units
{
    /** Unit keys stored in articles.unite, in menu order. */
    public const KEYS = [
        'Unite',
        'Piece',
        'Kg',
        'g',
        'L',
        'ml',
        'm',
        'cm',
        'm2',
        'Paquet',
        'Boite',
        'Carton',
        'Sac',
        'Lot',
        'Douzaine',
    ];

    /** Translated key => label list for a Select. */
    public static function options(): array
    {
        $options = [];

        foreach (self::KEYS as $key) {
            $options[$key] = self::label($key);
        }

        return $options;
    }

    /**
     * Label for a stored unit. Falls back to the raw value so articles saved
     * with a custom unit before this list existed still read correctly.
     */
    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return __('app.unite.'.$key) === 'app.unite.'.$key
            ? $key
            : __('app.unite.'.$key);
    }
}
