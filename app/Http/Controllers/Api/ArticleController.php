<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Barcode;
use App\Support\Units;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Articles, with the rules of the former Filament form (ArticleResource):
 * generated EAN-13 by default, checksum checked, fixed unit list (plus the
 * unit an older article already carries), unique reference and barcode.
 */
class ArticleController extends Controller
{
    private const SORTS = ['designation', 'reference', 'prix_vente', 'stock', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'categorie' => ['nullable', 'integer'],
            'stock' => ['nullable', Rule::in(['rupture', 'bas'])],
            'actif' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = trim($data['q'] ?? '');

        $page = Article::query()
            ->with('category:id,nom')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('designation', 'like', "%{$q}%")
                ->orWhere('reference', 'like', "%{$q}%")
                ->orWhere('code_barre', 'like', "%{$q}%")))
            ->when($data['categorie'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when(($data['stock'] ?? null) === 'rupture', fn ($query) => $query->where('stock', '<=', 0))
            ->when(($data['stock'] ?? null) === 'bas', fn ($query) => $query->where('stock', '>', 0)->where('stock', '<=', 5))
            ->when(isset($data['actif']), fn ($query) => $query->where('actif', (bool) $data['actif']))
            ->orderBy($data['sort'] ?? 'designation', $data['dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 25)
            ->through(fn (Article $a) => $this->row($a));

        return response()->json($page);
    }

    /** Defaults for a new article, as the form pre-filled them. */
    public function nouveau(): JsonResponse
    {
        return response()->json([
            'reference' => 'ART-'.str_pad((string) (Article::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'code_barre' => Barcode::generate(),
            'unite' => 'Unite',
            'unites' => $this->unites(null),
        ]);
    }

    /** A fresh unused EAN-13 (the "regenerate" button). */
    public function codeBarre(): JsonResponse
    {
        return response()->json(['code_barre' => Barcode::generate()]);
    }

    public function show(Article $article): JsonResponse
    {
        return response()->json($this->row($article->load('category:id,nom')) + ['unites' => $this->unites($article)]);
    }

    public function store(Request $request): JsonResponse
    {
        $article = Article::create($this->validated($request, null));

        return response()->json($this->row($article->load('category:id,nom')), 201);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $article->update($this->validated($request, $article));

        return response()->json($this->row($article->load('category:id,nom')));
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Article $article): array
    {
        $data = $request->validate([
            'designation' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:60', Rule::unique('articles', 'reference')->ignore($article)],
            'code_barre' => [
                'nullable', 'string', 'max:64', Rule::unique('articles', 'code_barre')->ignore($article),
                // A scanner never produces an EAN-13 whose check digit is off.
                function (string $attribute, $value, \Closure $fail) {
                    $code = trim((string) $value);
                    if (preg_match('/^\d{13}$/', $code) && ! Barcode::isValidEan13($code)) {
                        $fail(__('app.article.code_barre_checksum'));
                    }
                },
            ],
            'unite' => ['required', Rule::in(array_keys($this->unites($article)))],
            'prix_vente' => ['required', 'numeric', 'min:0'],
            'prix_achat' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric'],
            'tva' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'actif' => ['boolean'],
            'marque' => ['nullable', 'string', 'max:60'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ]);

        $data['code_barre'] = isset($data['code_barre']) && trim($data['code_barre']) !== '' ? trim($data['code_barre']) : null;
        $data['prix_achat'] ??= 0;
        $data['stock'] ??= 0;
        $data['tva'] ??= 20;

        return $data;
    }

    /**
     * The standard list, plus whatever the record already holds so an
     * article saved before the list existed keeps its unit.
     *
     * @return array<string, string>
     */
    private function unites(?Article $article): array
    {
        $current = $article?->unite;

        return Units::options() + ($current ? [$current => $current] : []);
    }

    /** @return array<string, mixed> */
    private function row(Article $a): array
    {
        return [
            'id' => $a->id,
            'designation' => $a->designation,
            'reference' => $a->reference,
            'code_barre' => $a->code_barre,
            'code_barre_standard' => $a->code_barre ? Barcode::isStandardRetail($a->code_barre) : null,
            'unite' => $a->unite,
            'unite_label' => Units::label($a->unite),
            'marque' => $a->marque,
            'category_id' => $a->category_id,
            'category' => $a->category?->nom,
            'prix_achat' => (float) $a->prix_achat,
            'prix_vente' => (float) $a->prix_vente,
            'stock' => (float) $a->stock,
            'tva' => (float) $a->tva,
            'actif' => (bool) $a->actif,
            'label_url' => $a->code_barre ? route('article.label', $a) : null,
            'barcode_url' => $a->code_barre ? route('article.barcode', $a) : null,
        ];
    }
}
