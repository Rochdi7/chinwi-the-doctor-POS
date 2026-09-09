<?php

namespace App\Support;

use App\Models\Article;
use Illuminate\Support\Str;

/**
 * Turns what the scanner typed into a sale line.
 *
 * A USB scanner is just a keyboard: it types the digits and presses Enter.
 * The cashier never touches the mouse, so everything here has to work from
 * that single string.
 */
class ScanCart
{
    /**
     * Add a scanned article to the current repeater state.
     *
     * Scanning the same article twice bumps the quantity instead of opening
     * a second line — three tins of the same milk is one line of 3, which is
     * what the customer sees on the receipt.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{items: array<int|string, array<string, mixed>>, article: Article|null, added: bool}
     */
    public static function add(array $items, string $code, Article $article): array
    {
        foreach ($items as $key => $line) {
            if ((int) ($line['article_id'] ?? 0) !== $article->id) {
                continue;
            }

            // Blank quantity means the row was never filled in, not zero.
            $current = ($line['quantite'] ?? '') === '' ? 0.0 : (float) $line['quantite'];
            $items[$key]['quantite'] = $current + 1;

            return ['items' => $items, 'article' => $article, 'added' => false];
        }

        // Filament keys repeater rows by a random string; any unique key works
        // as long as it does not collide with an existing row.
        $items[(string) Str::uuid()] = [
            'article_id' => $article->id,
            'designation' => $article->designation,
            'quantite' => 1,
            'prix_unitaire' => (float) $article->prix_vente,
            'remise' => 0,
            'tva' => (float) $article->tva,
        ];

        return ['items' => $items, 'article' => $article, 'added' => true];
    }

    /**
     * Drop the empty line Filament starts the form with, so the first scan
     * does not leave a blank row above it.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array<int|string, array<string, mixed>>
     */
    public static function withoutBlankRows(array $items): array
    {
        return array_filter(
            $items,
            fn ($line) => ! empty($line['article_id']) || ! empty($line['designation'])
        );
    }
}
