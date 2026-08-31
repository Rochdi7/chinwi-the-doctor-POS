<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArabicUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => bcrypt('admin1234'),
        ]);
    }

    public function test_arabic_panel_renders_rtl_with_arabic_navigation(): void
    {
        $this->actingAs($this->admin());
        session(['locale' => 'ar']);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $html, 'html tag carries rtl');

        foreach (['البيع', 'المعطيات', 'الصندوق', 'الأمان'] as $group) {
            $this->assertStringContainsString($group, $html, "group {$group} translated");
        }

        // Règlements has no menu entry any more: payments are taken at the till.
        foreach (['المبيعات', 'السلع', 'الزبناء'] as $item) {
            $this->assertStringContainsString($item, $html, "item {$item} translated");
        }
    }

    /**
     * Each locale gets its own request: the panel memoises navigation labels
     * per process, so two locales in one test would compare stale markup.
     */
    #[DataProvider('localeGroupOrder')]
    public function test_navigation_groups_keep_the_same_order(string $locale, array $groups): void
    {
        $this->actingAs($this->admin());
        session(['locale' => $locale]);

        $this->get('/admin')->assertOk();

        // Read the panel's own navigation rather than searching the rendered
        // HTML: a group name that is also a substring of an item label
        // ("Caisse" inside "Caisse rapide") makes string positions lie.
        $panel = \Filament\Facades\Filament::getPanel('admin');
        \Filament\Facades\Filament::setCurrentPanel($panel);

        $rendered = collect($panel->getNavigation())
            ->map(fn ($group) => $group->getLabel())
            ->filter()
            ->values()
            ->all();

        $positions = [];
        foreach ($groups as $key => $label) {
            $at = array_search($label, $rendered, true);
            $this->assertNotFalse($at, "{$label} present in {$locale}");
            $positions[$key] = $at;
        }

        asort($positions);

        $this->assertSame(
            ['vente', 'donnees', 'caisse', 'securite'],
            array_keys($positions),
            "{$locale} sidebar runs Vente → Données → Caisse → Sécurité, never reversed",
        );
    }

    public static function localeGroupOrder(): array
    {
        return [
            'french' => ['fr', [
                'vente' => 'Vente',
                'donnees' => 'Données',
                'caisse' => 'Caisse',
                'securite' => 'Sécurité',
            ]],
            'arabic' => ['ar', [
                'vente' => 'البيع',
                'donnees' => 'المعطيات',
                'caisse' => 'الصندوق',
                'securite' => 'الأمان',
            ]],
            'darija' => ['ary', [
                'vente' => 'البيع',
                'donnees' => 'المعلومات',
                'caisse' => 'الكيس',
                'securite' => 'الأمان',
            ]],
        ];
    }

    public function test_darija_panel_renders_rtl_with_spoken_moroccan_labels(): void
    {
        $this->actingAs($this->admin());
        session(['locale' => 'ary']);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $html, 'html tag carries rtl');

        // Darija words the Fusha file does not use: if these appear, the
        // ary file is being read rather than falling back to ar.
        foreach (['الكيس', 'المعلومات', 'البيوعات', 'الشراية'] as $word) {
            $this->assertStringContainsString($word, $html, "darija term {$word} rendered");
        }

        // Fusha terms Darija replaces must not leak through.
        foreach (['الصندوق', 'المعطيات', 'الزبناء'] as $fusha) {
            $this->assertStringNotContainsString($fusha, $html, "fusha term {$fusha} replaced");
        }
    }

    /** Every key in the reference locale must exist in the others, or the UI shows raw key paths. */
    #[DataProvider('translatedLocales')]
    public function test_locale_defines_every_key(string $locale): void
    {
        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($items as $key => $value) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
                $out = is_array($value)
                    ? array_merge($out, $flatten($value, $path))
                    : array_merge($out, [$path]);
            }

            return $out;
        };

        $reference = $flatten(require lang_path('fr/app.php'));
        $actual = $flatten(require lang_path("{$locale}/app.php"));

        $this->assertSame([], array_values(array_diff($reference, $actual)), "{$locale} is missing keys");
        $this->assertSame([], array_values(array_diff($actual, $reference)), "{$locale} has unknown keys");
    }

    public static function translatedLocales(): array
    {
        return ['arabic' => ['ar'], 'darija' => ['ary']];
    }
}
