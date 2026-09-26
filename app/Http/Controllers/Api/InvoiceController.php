<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ventes (InvoiceResource): list, detail, the create/edit form, Encaisser.
 * Totals and statut are always recomputed by the model; they are never
 * accepted from the client.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'client_id' => ['nullable', 'integer'],
            'statut' => ['nullable', Rule::in(Invoice::STATUTS)],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['date_facture', 'numero', 'total_ttc'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = trim($data['q'] ?? '');

        $page = Invoice::query()
            ->with('client:id,raison_sociale')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('numero', 'like', "%{$q}%")
                ->orWhereHas('client', fn ($c) => $c->where('raison_sociale', 'like', "%{$q}%"))))
            ->when($data['client_id'] ?? null, fn ($query, $id) => $query->where('client_id', $id))
            ->when($data['statut'] ?? null, fn ($query, $s) => $query->where('statut', $s))
            ->when($data['du'] ?? null, fn ($query, $d) => $query->whereDate('date_facture', '>=', $d))
            ->when($data['au'] ?? null, fn ($query, $d) => $query->whereDate('date_facture', '<=', $d))
            ->orderBy($data['sort'] ?? 'date_facture', $data['dir'] ?? 'desc')
            ->orderBy('id', $data['dir'] ?? 'desc')
            ->paginate($data['per_page'] ?? 25)
            ->through(fn (Invoice $i) => $this->row($i));

        return response()->json($page);
    }

    /** What the create form starts with. */
    public function nouveau(): JsonResponse
    {
        return response()->json([
            'numero' => Invoice::nextNumero(),
            'date_facture' => now()->toDateString(),
        ]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['client', 'items', 'payments', 'user:id,name']);

        return response()->json($this->row($invoice) + [
            'note' => $invoice->note,
            'total_ht' => (float) $invoice->total_ht,
            'total_tva' => (float) $invoice->total_tva,
            'user' => $invoice->user?->name,
            'items' => $invoice->items->map(fn (InvoiceItem $l) => [
                'id' => $l->id,
                'article_id' => $l->article_id,
                'designation' => $l->designation,
                'quantite' => (float) $l->quantite,
                'prix_unitaire' => (float) $l->prix_unitaire,
                'remise' => (float) $l->remise,
                'tva' => (float) $l->tva,
                'total_ht' => (float) $l->total_ht,
                'total_ttc' => (float) $l->total_ttc,
            ]),
            'payments' => $invoice->payments->sortByDesc('id')->values()->map(fn (Payment $p) => [
                'id' => $p->id,
                'date' => $p->date_paiement?->toDateString(),
                'mode' => $p->mode,
                'reference' => $p->reference,
                'montant' => (float) $p->montant,
                'pdf_url' => route('payment.pdf', $p),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $paiement = $request->validate([
            'encaisser' => ['boolean'],
            'mode' => ['nullable', Rule::in(['especes', 'tpe'])],
            'montant_recu' => ['nullable', 'numeric', 'min:0'],
        ]);

        [$invoice, $payment] = $this->invoices->creer($data, $data['items'], [
            'encaisser' => (bool) ($paiement['encaisser'] ?? false),
            'mode' => $paiement['mode'] ?? 'especes',
            'montant' => $paiement['montant_recu'] ?? null,
        ]);

        return response()->json([
            'invoice' => $this->row($invoice),
            'payment' => $payment ? ['id' => $payment->id, 'montant' => (float) $payment->montant, 'pdf_url' => route('payment.pdf', $payment)] : null,
        ], 201);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $this->validated($request, $invoice);

        return response()->json($this->row($this->invoices->modifier($invoice, $data, $data['items'])));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json(null, 204);
    }

    /** "Encaisser": a payment of at most what is still owed. */
    public function encaisser(Request $request, Invoice $invoice): JsonResponse
    {
        $reste = $invoice->reste();

        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01', 'max:'.max($reste, 0)],
            'mode' => ['required', Rule::in(['especes', 'tpe'])],
        ]);

        $payment = $this->invoices->encaisser($invoice, (float) $data['montant'], $data['mode']);

        return response()->json([
            'invoice' => $this->row($invoice->refresh()),
            'payment' => ['id' => $payment->id, 'montant' => (float) $payment->montant, 'pdf_url' => route('payment.pdf', $payment)],
        ], 201);
    }

    /** @return array<string, mixed> header and lines, as the Filament form validated them */
    private function validated(Request $request, ?Invoice $invoice): array
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'date_facture' => ['required', 'date'],
            'numero' => ['required', 'string', 'max:40', Rule::unique('invoices', 'numero')->ignore($invoice)],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['present', 'array', 'max:500'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')],
            'items.*.designation' => ['required', 'string', 'max:255'],
            'items.*.quantite' => ['required', 'numeric'],
            'items.*.prix_unitaire' => ['required', 'numeric'],
            'items.*.remise' => ['nullable', 'numeric', 'min:0'],
            'items.*.tva' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // A discount larger than the line would be clamped to 0 by
        // computeTotals(); refuse it instead, as the form did.
        $errors = [];
        foreach ($data['items'] as $i => $line) {
            $brut = (float) $line['quantite'] * (float) $line['prix_unitaire'];
            if ((float) ($line['remise'] ?? 0) > $brut + 0.0001) {
                $errors["items.$i.remise"] = __('validation.max.numeric', ['attribute' => __('app.item.remise'), 'max' => $brut]);
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function row(Invoice $i): array
    {
        return [
            'id' => $i->id,
            'numero' => $i->numero,
            'date_facture' => $i->date_facture?->toDateString(),
            'client_id' => $i->client_id,
            'client' => $i->client?->raison_sociale,
            'statut' => $i->statut,
            'total_ttc' => (float) $i->total_ttc,
            'montant_paye' => (float) $i->montant_paye,
            'reste' => max($i->reste(), 0),
            'pdf_url' => route('invoice.pdf', $i),
        ];
    }
}
