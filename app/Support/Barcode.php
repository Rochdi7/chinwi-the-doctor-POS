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

    /**
     * White space either side of the bars, in modules. GS1 asks for 11 on
     * the left of an EAN-13 and 7 on the right; without it a scanner cannot
     * tell where the code starts and simply stays silent.
     */
    private const QUIET_MODULES = 11;

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
     * A USB scanner sends key positions, not characters. On a French
     * (AZERTY) Windows layout the unshifted digit row is punctuation, so a
     * scanner left on its US default types `é"'(-è_çà&` for `2345678901`.
     * Map that back rather than making every shop reprogram its scanner.
     */
    private const AZERTY_DIGITS = [
        '&' => '1', 'é' => '2', '"' => '3', "'" => '4', '(' => '5',
        '-' => '6', 'è' => '7', '_' => '8', 'ç' => '9', 'à' => '0',
    ];

    /**
     * What the scanner actually meant. Only a code made entirely of the
     * AZERTY digit-row characters is translated: a real reference such as
     * ART-0001 contains a dash too, and must be left alone.
     */
    public static function normalizeScan(string $code): string
    {
        $code = trim($code);

        if ($code !== '' && preg_match('/^[&é"\'(\-è_çà]{8,}$/u', $code)) {
            return strtr($code, self::AZERTY_DIGITS);
        }

        return $code;
    }

    /**
     * What a retail scanner expects on a product: EAN-8, UPC-A, EAN-13 or
     * GTIN-14, all digits. Anything else was typed by hand and will never
     * match what the scanner reads off the packaging.
     */
    public static function isStandardRetail(string $code): bool
    {
        return (bool) preg_match('/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $code);
    }

    /**
     * The printable label: bars with quiet zones and the digits underneath.
     *
     * Codes we did not generate (a real product barcode typed in by hand)
     * may be any length, so fall back to Code 128 which encodes anything.
     */
    public static function png(string $code, int $widthFactor = 3, int $height = 60): string
    {
        $generator = new BarcodeGeneratorPNG;

        $bars = $generator->getBarcode(
            $code,
            self::isValidEan13($code) ? $generator::TYPE_EAN_13 : $generator::TYPE_CODE_128,
            $widthFactor,
            $height,
        );

        return self::label($bars, $code, $widthFactor);
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

    /**
     * Put the bare bars on a white label: quiet zone left and right, the
     * code printed under the bars so a torn label can still be keyed in.
     */
    private static function label(string $barsPng, string $code, int $widthFactor): string
    {
        $bars = imagecreatefromstring($barsPng);
        $barsW = imagesx($bars);
        $barsH = imagesy($bars);

        $quiet = self::QUIET_MODULES * $widthFactor;
        $font = 5;
        $pad = 4;
        $textW = imagefontwidth($font) * strlen($code);
        $textH = imagefontheight($font);

        $width = max($barsW + 2 * $quiet, $textW + 2 * $pad);
        $height = $pad + $barsH + $pad + $textH + $pad;

        $label = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($label, 255, 255, 255);
        $black = imagecolorallocate($label, 0, 0, 0);
        imagefill($label, 0, 0, $white);

        // Centre the bars; the source background is transparent and is
        // skipped by imagecopy, so only the ink lands on the white.
        imagecopy($label, $bars, (int) (($width - $barsW) / 2), $pad, 0, 0, $barsW, $barsH);
        imagestring($label, $font, (int) (($width - $textW) / 2), $pad + $barsH + $pad, $code, $black);

        ob_start();
        imagepng($label);
        $png = ob_get_clean();

        imagedestroy($bars);
        imagedestroy($label);

        return $png;
    }
}
