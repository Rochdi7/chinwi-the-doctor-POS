<?php

namespace App\Support;

use App\Models\Article;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class Barcode
{
    /**
     * Internal EAN-13 codes start with 2: the GS1 range reserved for
     * in-store use, so a generated code can never collide with a real
     * manufacturer barcode printed on a product.
     */
    private const PREFIX = '2';

    /** Generate a unique EAN-13 not already used by another article. */
    public static function generate(): string
    {
        do {
            $body = self::PREFIX.str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);
            $code = $body.self::checksum($body);
        } while (Article::where('code_barre', $code)->exists());

        return $code;
    }

    /** EAN-13 check digit: weights 1 and 3 alternating, complement to 10. */
    public static function checksum(string $twelveDigits): int
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    public static function isValidEan13(string $code): bool
    {
        return (bool) preg_match('/^\d{13}$/', $code)
            && (int) $code[12] === self::checksum(substr($code, 0, 12));
    }

    /**
     * Codes we did not generate (a real product barcode typed in by hand)
     * may be any length, so fall back to Code 128 which encodes anything.
     */
    public static function png(string $code, int $widthFactor = 3, int $height = 60): string
    {
        $generator = new BarcodeGeneratorPNG;

        return $generator->getBarcode(
            $code,
            self::isValidEan13($code) ? $generator::TYPE_EAN_13 : $generator::TYPE_CODE_128,
            $widthFactor,
            $height,
        );
    }

    public static function svg(string $code, int $widthFactor = 2, int $height = 50): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode(
            $code,
            self::isValidEan13($code) ? $generator::TYPE_EAN_13 : $generator::TYPE_CODE_128,
            $widthFactor,
            $height,
        );
    }

    /** Inline image for a Blade preview, so no file has to be written. */
    public static function dataUri(string $code): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($code));
    }
}
