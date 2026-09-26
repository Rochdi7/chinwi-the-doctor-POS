<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Categories for a phone shop: selling, accessories, charging and repair.
 * Categories have no icon column: the till picks the icon from the name
 * (resources/react/components/CategoryIcon.tsx), and each name here lands
 * on its own icon. Safe to run again: existing names are left alone.
 *
 *   php artisan db:seed --class=CategorySeeder
 */
class CategorySeeder extends Seeder
{
    public const NAMES = [
        // Phones & devices
        'Smartphones',
        'Tablettes',
        'Montres connectées',
        'Cartes SIM & recharges',

        // Charging & power
        'Chargeurs',
        'Câbles',
        'Batteries de rechange',

        // Accessories
        'Coques & étuis',
        'Verres trempés & protection',
        'Écouteurs & casques',
        'Enceintes Bluetooth',
        'Cartes mémoire & clés USB',
        'Accessoires auto',
        'Accessoires',

        // Repair
        'Écrans & afficheurs',
        'Pièces détachées',
        'Réparation & services',
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::NAMES as $nom) {
            $created += Category::firstOrCreate(['nom' => $nom])->wasRecentlyCreated ? 1 : 0;
        }

        $this->command?->info($created.' categories created, '.(count(self::NAMES) - $created).' already there.');
    }
}
