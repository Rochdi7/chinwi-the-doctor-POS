<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * A full catalogue of categories, phone shop first, then the general store.
 * Categories have no icon column: the till picks the icon from the name
 * (resources/react/components/CategoryIcon.tsx), and each name here lands
 * on its own icon. Safe to run again: existing names are left alone.
 *
 *   php artisan db:seed --class=CategorySeeder
 */
class CategorySeeder extends Seeder
{
    public const NAMES = [
        // Phones, computing, electronics
        'Smartphones',
        'Tablettes',
        'Ordinateurs portables',
        'Montres connectées',
        'Composants PC',
        'Consoles & jeux vidéo',
        'TV & télévision',
        'Enceintes Bluetooth',
        'Écouteurs & casques',
        'Câbles',
        'Chargeurs',
        'Écrans & afficheurs',
        'Batteries de rechange',
        'Cartes mémoire & clés USB',
        'Disques durs & SSD',
        'Souris',
        'Claviers',
        'Imprimantes & encre',
        'Réseau & Wi-Fi',
        'Appareils photo & caméras',
        'Cartes SIM & recharges',
        'Ampoules & éclairage',
        'Rallonges & prises',
        'Ventilateurs & climatisation',
        'Pièces détachées',
        'Coques & étuis',
        'Verres trempés & protection',
        'Réparation & services',
        'Outillage & bricolage',
        'Accessoires auto',
        'Accessoires',

        // Food
        'Épicerie',
        'Boissons',
        'Café & thé',
        'Produits laitiers',
        'Boulangerie',
        'Pâtisserie',
        'Fruits & légumes',
        'Boucherie & volaille',
        'Poissonnerie',
        'Œufs',
        'Céréales & farines',
        'Confiserie & biscuits',
        'Glaces',
        'Sandwichs & snacks',
        'Surgelés',

        // Household, personal, leisure
        'Hygiène & soins',
        'Beauté & parfums',
        'Parapharmacie',
        'Produits d\'entretien',
        'Bébé & puériculture',
        'Vêtements',
        'Papeterie & fournitures',
        'Livres & magazines',
        'Jouets',
        'Cadeaux',
        'Sport & fitness',
        'Animaux',
        'Jardin & plantes',
        'Maison & déco',
        'Cuisine & ustensiles',
        'Tabac',
        'Gaz & charbon',
        'Bijoux',
        'Lunettes',
        'Vélos & trottinettes',
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
