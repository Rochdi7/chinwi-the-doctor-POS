<?php

namespace Tests\Feature;

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Straight apostrophes survive Blade's {{ }} as `&#039;` and @js() as `\u0027`.
 * Filament pushes labels through both, so a label like "Prix d'achat" could
 * surface to the user as escaped source instead of text. Typographic
 * apostrophes (U+2019) pass every layer untouched, and read better in French.
 */
class TranslationQuotingTest extends TestCase
{
    // Every other Feature test refreshes the database; a test that opts out
    // mid-suite leaves the shared schema dropped for the ones that follow.
    use RefreshDatabase;

    public static function localeProvider(): array
    {
        return array_map(fn (string $l) => [$l], array_keys(Locales::SUPPORTED));
    }

    #[DataProvider("localeProvider")]
    public function test_translations_use_no_raw_quotes(string $locale): void
    {
        $offenders = [];

        $file = lang_path("{$locale}/app.php");
        $this->assertFileExists($file);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator(require $file)
        );

        foreach ($iterator as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            // ' and " break JS/HTML escaping; < & > would be mangled by e().
            if (preg_match('/[\'"<>&]/', $value)) {
                $path = [];

                for ($i = 0; $i < $iterator->getDepth(); $i++) {
                    $path[] = $iterator->getSubIterator($i)->key();
                }

                $path[] = $key;
                $offenders[] = implode('.', $path).' = '.$value;
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Locale [%s] has strings with characters that escape badly in HTML/JS.\n".
            "Use the typographic apostrophe ’ (U+2019) instead of '.\n%s",
            $locale,
            implode("\n", $offenders)
        ));
    }
}
