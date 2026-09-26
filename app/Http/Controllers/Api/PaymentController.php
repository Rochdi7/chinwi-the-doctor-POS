<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Règlements (PaymentResource): the list, editing and deleting a payment.
 * PaymentObserver keeps invoice, client balance and drawer in step, with
 * its existing behaviour (see the audit: an edit does not move the drawer).
 */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['especes', 'tpe'])],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = trim($data['q'] ?? '');

        $page = Payment::query()
            ->with(['client:id,raison_sociale', 'invoice:id,numero'])
            ->when($data['mode'] ?? null, fn ($query, $m) => $query->where('mode', $m))
            ->when($data['du'] ?? null, fn ($query, $d) => $query->whereDate('date_paiement', '>=', $d))
            ->when($data['au'] ?? null, fn ($query, $d) => $query->whereDate('date_paiement', '<=', $d))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereHas('invoice', fn ($i) => $i->where('numero', 'like', "%{$q}%"))
                ->orWhereHas('client', fn ($c) => $c->where('raison_sociale', 'like', "%{$q}%"))))
            ->orderByDesc('date_paiement')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 25)
            ->through(fn (Payment $p) => $this->row($p));

        return response()->json($page);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($this->row($payment->load(['client:id,raison_sociale', 'invoice:id,numero'])));
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_paiement' => ['required', 'date'],
            'mode' => ['required', Rule::in(['especes', 'tpe'])],
            'reference' => ['nullable', 'string', 'max:60'],
        ]);

        DB::transaction(function () use ($payment, $data) {
            // Never more than the invoice still owes, this payment's own share included.
            if ($data['invoice_id'] ?? null) {
                $invoice = Invoice::whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();
                $max = max($invoice->reste() + ($payment->invoice_id === $invoice->id ? (float) $payment->montant : 0), 0);

                if ((float) $data['montant'] > $max + 0.001) {
                    throw ValidationException::withMessages(['montant' => __('validation.max.numeric', ['attribute' => __('validation.attributes.montant'), 'max' => $max])]);
                }
            }

            $payment->update($data);
        });

        return response()->json($this->row($payment->load(['client:id,raison_sociale', 'invoice:id,numero'])));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function row(Payment $p): array
    {
        return [
            'id' => $p->id,
            'date_paiement' => $p->date_paiement?->toDateString(),
            'client_id' => $p->client_id,
            'client' => $p->client?->raison_sociale,
            'invoice_id' => $p->invoice_id,
            'numero' => $p->invoice?->numero,
            'montant' => (float) $p->montant,
            'mode' => $p->mode,
            'reference' => $p->reference,
            'pdf_url' => route('payment.pdf', $p),
        ];
    }
}
