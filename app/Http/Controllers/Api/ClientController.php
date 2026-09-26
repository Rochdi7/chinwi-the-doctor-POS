<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Clients (ClientResource). The balance is kept by the observers, never typed. */
class ClientController extends Controller
{
    private const SORTS = ['raison_sociale', 'solde', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'doit' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = trim($data['q'] ?? '');

        $page = Client::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('raison_sociale', 'like', "%{$q}%")
                ->orWhere('telephone', 'like', "%{$q}%")
                ->orWhere('ice', 'like', "%{$q}%")))
            ->when($data['doit'] ?? false, fn ($query) => $query->where('solde', '>', 0))
            ->orderBy($data['sort'] ?? 'raison_sociale', $data['dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 25)
            ->through(fn (Client $c) => $this->row($c));

        return response()->json($page);
    }

    /** Short list for pickers (sale form, filters), searched on the server. */
    public function options(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => Client::query()
            ->when($q !== '', fn ($query) => $query->where('raison_sociale', 'like', "%{$q}%"))
            ->orderBy('raison_sociale')
            ->limit(50)
            ->get(['id', 'raison_sociale'])]);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json($this->row($client) + [
            'invoices' => $client->invoices()->orderByDesc('date_facture')->orderByDesc('id')->limit(50)->get()
                ->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'numero' => $i->numero,
                    'date' => $i->date_facture?->toDateString(),
                    'statut' => $i->statut,
                    'total_ttc' => (float) $i->total_ttc,
                    'reste' => max($i->reste(), 0),
                ]),
            'payments' => $client->payments()->with('invoice:id,numero')->orderByDesc('date_paiement')->orderByDesc('id')->limit(50)->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'date' => $p->date_paiement?->toDateString(),
                    'numero' => $p->invoice?->numero,
                    'mode' => $p->mode,
                    'montant' => (float) $p->montant,
                    'pdf_url' => route('payment.pdf', $p),
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->row(Client::create($this->validated($request))), 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $client->update($this->validated($request));

        return response()->json($this->row($client));
    }

    /** Its sales stay, as walk-in sales (client_id is nullOnDelete). */
    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:1000'],
            'ice' => ['nullable', 'string', 'max:40'],
            'rc' => ['nullable', 'string', 'max:40'],
            'numero_compte' => ['nullable', 'string', 'max:40'],
            'actif' => ['boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Client $c): array
    {
        return [
            'id' => $c->id,
            'raison_sociale' => $c->raison_sociale,
            'telephone' => $c->telephone,
            'email' => $c->email,
            'adresse' => $c->adresse,
            'ice' => $c->ice,
            'rc' => $c->rc,
            'numero_compte' => $c->numero_compte,
            'solde' => (float) $c->solde,
            'actif' => (bool) $c->actif,
        ];
    }
}
