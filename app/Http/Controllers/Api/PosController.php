<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ApercuRequest;
use App\Http\Requests\Pos\VenteRequest;
use App\Models\Article;
use App\Models\CaisseMouvement;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\SaleService;
use App\Support\Barcode;
use App\Support\ScanQueue;
use App\Support\ScannerUsb;
use App\Support\Units;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The till, as JSON, for the React app.
 *
 * Every endpoint is a thin layer over what the Livewire till already does:
 * the article query of PosGrille, Article::findByScan(), ScanQueue for the
 * phone, ScannerUsb for the USB scanner, and SaleService for the sale. The
 * client sends ids and quantities; prices, VAT and totals are always read
 * or computed here.
 */
class PosController extends Controller
{
    /** Grid tiles: enough to browse, few enough to stay quick (as PosGrille). */
    private const LIMIT = 60;

    public function __construct(private readonly SaleService $sales) {}

    /** Everything the till needs once, when it opens. */
    public function init(Request $request): JsonResponse
    {
        ScannerUsb::refresh();
        $till = ScanQueue::tillFor($request->user()->id);

        return response()->json([
            'categories' => Category::query()->orderBy('nom')->get(['id', 'nom']),
            'clients' => Client::query()->orderBy('raison_sociale')->limit(200)->get(['id', 'raison_sociale']),
            'till' => $till,
            // The phone scanner page of the React app, pointed at this till.
            'scanner_url' => url('/app/scanner').'?till='.$till,
            'usb' => $this->usb(),
        ]);
    }

    /** Same query as the Livewire grid (App\Livewire\PosGrille::articles). */
    public function articles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'categorie' => ['nullable', 'integer'],
        ]);

        $search = trim($data['recherche'] ?? '');

        $articles = Article::query()
            ->where('actif', true)
            ->when($data['categorie'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('designation', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('code_barre', 'like', "%{$search}%")))
            ->orderBy('designation')
            ->limit(self::LIMIT)
            ->get();

        return response()->json(['data' => $articles->map(fn (Article $a) => $this->article($a))]);
    }

    /** One scanned (or typed) code, resolved the way the till resolves it. */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $result = $this->resolve($data['code']);

        return response()->json($result, $result['article'] ? 200 : 404);
    }

    /**
     * Codes pushed by a paired phone, plus the USB scanner state. Polled by
     * the till like PointDeVente::recupererScans().
     */
    public function scans(Request $request): JsonResponse
    {
        ScannerUsb::refresh();

        $codes = ScanQueue::drain(ScanQueue::tillFor($request->user()->id));

        return response()->json([
            'scans' => array_map(fn (string $code) => $this->resolve($code), $codes),
            'usb' => $this->usb(),
        ]);
    }

    /** Totals as they will be saved, for what is in the cart right now. */
    public function apercu(ApercuRequest $request): JsonResponse
    {
        $lignes = $this->sales->lignesDepuisArticles($request->validated('items'));
        $apercu = $this->sales->apercu($lignes);
        $recu = $request->validated('montant_recu');

        return response()->json($apercu + [
            'rendu' => $this->sales->rendu($apercu['total_ttc'], $recu === null ? null : (float) $recu),
            'avertissements' => $this->stockWarnings($lignes),
        ]);
    }

    /** Encaisser (encaisser=true) or save on credit (encaisser=false). */
    public function vente(VenteRequest $request): JsonResponse
    {
        $data = $request->validated();

        // A double tap or a retried request must not sell twice. The same key
        // from the same cashier is refused while it is being handled, and
        // once handled the first answer is replayed instead of a new sale.
        $key = 'pos-vente:'.$request->user()->id.':'.$data['cle'];
        $lock = Cache::lock($key.':lock', 30);

        if (! $lock->get()) {
            return response()->json(['message' => __('app.spa.erreur.doublon')], 409);
        }

        try {
            if ($done = Cache::get($key)) {
                return response()->json($done, 200);
            }

            $lignes = $this->sales->lignesDepuisArticles($data['items']);
            $recu = isset($data['montant_recu']) ? (float) $data['montant_recu'] : null;

            [$invoice, $payment] = $this->sales->enregistrer(
                $lignes,
                $data['client_id'] ?? null,
                (bool) $data['encaisser'],
                $recu,
                $data['mode'],
            );

            $response = $this->venteResponse($invoice, $payment, $recu);
            Cache::put($key, $response, 600);
        } finally {
            $lock->release();
        }

        return response()->json($response, 201);
    }

    /** @return array<string, mixed> */
    private function venteResponse(Invoice $invoice, ?Payment $payment, ?float $recu): array
    {
        $rendu = $this->sales->rendu((float) $invoice->total_ttc, $recu);

        return [
            'message' => __('app.pos.vente_ok', ['numero' => $invoice->numero]),
            'invoice' => [
                'id' => $invoice->id,
                'numero' => $invoice->numero,
                'total_ttc' => (float) $invoice->total_ttc,
                'montant_paye' => (float) $invoice->montant_paye,
                'statut' => $invoice->statut,
                'pdf_url' => route('invoice.pdf', $invoice),
                'recu_url' => route('invoice.receipt', $invoice),
            ],
            'payment' => $payment ? [
                'id' => $payment->id,
                'montant' => (float) $payment->montant,
                'mode' => $payment->mode,
                'pdf_url' => route('payment.pdf', $payment),
            ] : null,
            'monnaie' => $payment ? $rendu['monnaie'] : 0.0,
        ];
    }

    /**
     * Today at this till: sales, what was invoiced and what cash came in.
     * `caisse` is the drawer's net for the day as the cash book records it
     * (every entree minus every sortie, manual ones and reversals included),
     * so it matches the Caisse page. Shown in the header and refreshed after
     * each sale.
     */
    public function journee(): JsonResponse
    {
        $today = now()->toDateString();

        $caisse = CaisseMouvement::query()
            ->whereDate('occurred_at', $today)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'entree' THEN montant ELSE -montant END), 0) AS net")
            ->value('net');

        return response()->json([
            'ventes' => Invoice::query()->whereDate('date_facture', $today)->count(),
            'total' => (float) Invoice::query()->whereDate('date_facture', $today)->sum('total_ttc'),
            'especes' => (float) Payment::query()->whereDate('date_paiement', $today)->where('mode', 'especes')->sum('montant'),
            'caisse' => round((float) $caisse, 2),
        ]);
    }

    /** @return array{code: string, article: array<string, mixed>|null, message: string, stock_zero: bool} */
    private function resolve(string $raw): array
    {
        $code = Barcode::normalizeScan($raw);
        $article = $code === '' ? null : Article::findByScan($code);

        if (! $article) {
            return [
                'code' => $code,
                'article' => null,
                'message' => __('app.scan.introuvable', ['code' => $code]),
                'stock_zero' => false,
            ];
        }

        $stockZero = (float) $article->stock <= 0;

        return [
            'code' => $code,
            'article' => $this->article($article),
            'message' => $stockZero
                ? __('app.scan.stock_zero', ['article' => $article->designation])
                : __('app.pos.ajoute', ['article' => $article->designation]),
            'stock_zero' => $stockZero,
        ];
    }

    /**
     * Selling into negative stock is allowed at the till (loose weight, a
     * delivery not yet keyed in): warn, never block.
     *
     * @param  array<int, array{article_id: int, designation: string}>  $lignes
     * @return array<int, array{article_id: int, message: string}>
     */
    private function stockWarnings(array $lignes): array
    {
        $ids = array_column($lignes, 'article_id');
        $stocks = Article::query()->whereKey($ids)->pluck('stock', 'id');

        $warnings = [];

        foreach ($lignes as $line) {
            if ((float) ($stocks[$line['article_id']] ?? 0) <= 0) {
                $warnings[] = [
                    'article_id' => $line['article_id'],
                    'message' => __('app.scan.stock_zero', ['article' => $line['designation']]),
                ];
            }
        }

        return $warnings;
    }

    /** @return array<string, mixed> */
    private function article(Article $a): array
    {
        return [
            'id' => $a->id,
            'designation' => $a->designation,
            'reference' => $a->reference,
            'code_barre' => $a->code_barre,
            'prix_vente' => (float) $a->prix_vente,
            'tva' => (float) $a->tva,
            'stock' => (float) $a->stock,
            'unite' => $a->unite,
            'unite_label' => Units::label($a->unite),
            'image_url' => $a->imageUrl(),
            'category_id' => $a->category_id,
        ];
    }

    /** @return array{state: string, name: ?string, label: ?string, aide: ?string} */
    private function usb(): array
    {
        $status = ScannerUsb::status();
        $known = $status['state'] !== 'unknown';

        return $status + [
            'label' => $known ? __('app.pos.usb.'.$status['state']) : null,
            'aide' => $known ? __('app.pos.usb.aide_'.$status['state']) : null,
        ];
    }
}
