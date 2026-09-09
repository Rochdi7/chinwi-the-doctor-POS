<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The product tiles of the till, kept out of the cart component on purpose.
 *
 * Livewire only re-renders a child when the child itself changed, so a scan
 * or a +/- on a cart line no longer rebuilds and re-sends sixty tiles. A tap
 * on a tile calls the parent page straight away ($parent.ajouter), one
 * round-trip, and this grid stays untouched.
 */
class PosGrille extends Component
{
    /** Enough tiles to browse, few enough to stay quick. */
    private const LIMIT = 60;

    public string $recherche = '';

    public ?int $categorie = null;

    #[Computed]
    public function articles(): Collection
    {
        $search = trim($this->recherche);

        return Article::query()
            ->where('actif', true)
            ->when($this->categorie, fn ($q) => $q->where('category_id', $this->categorie))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('designation', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('code_barre', 'like', "%{$search}%")))
            ->orderBy('designation')
            ->limit(self::LIMIT)
            ->get(['id', 'designation', 'reference', 'prix_vente', 'stock', 'unite', 'category_id']);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('nom')->get(['id', 'nom']);
    }

    public function render(): View
    {
        return view('livewire.pos-grille');
    }
}
