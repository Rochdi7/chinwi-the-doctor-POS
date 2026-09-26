<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Best-effort product name for a manufacturer barcode, from free public
 * databases. Many small-brand accessories are in none of them, so a miss is
 * normal: the quick-add form then simply asks for the name.
 */
class ProductLookup
{
    /** @return array{designation: string, marque: ?string}|null */
    public static function find(string $code): ?array
    {
        // Only real retail codes; in-store codes (prefix 2) are ours.
        if (! config('services.product_lookup.enabled')
            || ! Barcode::isStandardRetail($code)
            || str_starts_with($code, '2')) {
            return null;
        }

        // Misses are cached too, so a re-scan does not wait on the network again.
        $hit = Cache::remember('product-lookup:'.$code, now()->addDay(), fn () => self::openFoodFacts($code) ?? self::upcItemDb($code) ?? false);

        return $hit ?: null;
    }

    /** @return array{designation: string, marque: ?string}|null */
    private static function openFoodFacts(string $code): ?array
    {
        try {
            $product = Http::timeout(4)->acceptJson()
                ->get("https://world.openfoodfacts.org/api/v2/product/{$code}.json", ['fields' => 'product_name,brands'])
                ->json('product');
        } catch (\Throwable) {
            return null;
        }

        return self::result($product['product_name'] ?? null, $product['brands'] ?? null);
    }

    /** @return array{designation: string, marque: ?string}|null */
    private static function upcItemDb(string $code): ?array
    {
        try {
            $item = Http::timeout(4)->acceptJson()
                ->get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $code])
                ->json('items.0');
        } catch (\Throwable) {
            return null;
        }

        return self::result($item['title'] ?? null, $item['brand'] ?? null);
    }

    /** @return array{designation: string, marque: ?string}|null */
    private static function result(mixed $name, mixed $brand): ?array
    {
        $name = is_string($name) ? trim($name) : '';

        if ($name === '') {
            return null;
        }

        $brand = is_string($brand) ? trim(explode(',', $brand)[0]) : '';

        return ['designation' => mb_substr($name, 0, 255), 'marque' => $brand !== '' ? mb_substr($brand, 0, 60) : null];
    }
}
