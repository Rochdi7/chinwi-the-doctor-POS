<?php

namespace Tests\Support;

/**
 * Reads an EAN-13 back out of a rendered PNG, the way a scanner would.
 *
 * A barcode is only useful if a device can decode it, so the tests decode
 * the actual pixels rather than trusting that the generator was called
 * correctly. Bar widths are measured from a single scan line, normalised to
 * the module width, and matched against the L/G/R digit alphabets.
 */
class Ean13Decoder
{
    /** L-code patterns for digits 0-9, as bar/space module runs. */
    private const L = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011',
    ];

    /** G-code: the L pattern reversed and inverted. */
    private const G = [
        '0100111', '0110011', '0011011', '0100001', '0011101',
        '0111001', '0000101', '0010001', '0001001', '0010111',
    ];

    /** R-code: the complement of L. */
    private const R = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100',
    ];

    /** First digit is encoded by which halves use L vs G in the left block. */
    private const PARITY = [
        'LLLLLL' => '0', 'LLGLGG' => '1', 'LLGGLG' => '2', 'LLGGGL' => '3',
        'LGLLGG' => '4', 'LGGLLG' => '5', 'LGGGLL' => '6', 'LGLGLG' => '7',
        'LGLGGL' => '8', 'LGGLGL' => '9',
    ];

    public static function decode(string $png): ?string
    {
        $bits = self::readBits($png);

        if ($bits === null) {
            return null;
        }

        // 95 modules: 3 guard + 42 left + 5 centre + 42 right + 3 guard.
        if (strlen($bits) < 95) {
            return null;
        }

        $start = strpos($bits, '101');

        if ($start === false || strlen($bits) - $start < 95) {
            return null;
        }

        $bits = substr($bits, $start, 95);

        $left = substr($bits, 3, 42);
        $right = substr($bits, 50, 42);

        $digits = '';
        $parity = '';

        for ($i = 0; $i < 6; $i++) {
            $chunk = substr($left, $i * 7, 7);
            $index = array_search($chunk, self::L, true);

            if ($index !== false) {
                $parity .= 'L';
            } else {
                $index = array_search($chunk, self::G, true);

                if ($index === false) {
                    return null;
                }

                $parity .= 'G';
            }

            $digits .= $index;
        }

        for ($i = 0; $i < 6; $i++) {
            $index = array_search(substr($right, $i * 7, 7), self::R, true);

            if ($index === false) {
                return null;
            }

            $digits .= $index;
        }

        $first = self::PARITY[$parity] ?? null;

        return $first === null ? null : $first.$digits;
    }

    /**
     * Turn one horizontal scan line into a module bit string.
     */
    private static function readBits(string $png): ?string
    {
        $source = @imagecreatefromstring($png);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // The generator leaves the background transparent, which a scanner
        // sees as the white label it is printed on. Flatten onto white so
        // alpha is not misread as ink.
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $y = (int) ($height / 2);

        // Sample the middle row: dark pixel = 1.
        $row = '';
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $luma = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 3;
            $row .= $luma < 128 ? '1' : '0';
        }

        imagedestroy($image);

        // Measure run lengths, then divide by the narrowest to get modules.
        preg_match_all('/(0+|1+)/', trim($row, '0'), $runs);

        if ($runs[0] === []) {
            return null;
        }

        $unit = min(array_map('strlen', $runs[0]));

        if ($unit < 1) {
            return null;
        }

        $bits = '';
        foreach ($runs[0] as $run) {
            $bits .= str_repeat($run[0], (int) round(strlen($run) / $unit));
        }

        return $bits;
    }
}
