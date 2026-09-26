<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The Ventes back office: what the Filament pages did on save, moved here
 * so the API runs the same steps.
 *
 * - creer():    CreateInvoice (invoice, then its lines one by one, then the
 *               optional payment taken at the counter), in one transaction
 *               as Filament's CreateRecord ran it.
 * - modifier(): EditInvoice (header update, lines synced like the repeater:
 *               removed lines deleted, kept lines updated, new lines created),
 *               then recalcTotals().
 * - encaisser(): the "Encaisser" row action of the invoice list.
 *
 * Every line goes through Eloquent, so InvoiceItemObserver moves the stock
 * and writes the audit trail exactly as before.
 */
class InvoiceService
{
    /**
     * @param  array{client_id?: ?int, date_facture: string, numero: string, note?: ?string}  $data
     * @param  array<int, array<string, mixed>>  $lignes
     * @param  array{encaisser: bool, mode: string, montant: mixed}  $paiement
     * @return array{0: Invoice, 1: Payment|null}
     */
    public function creer(array $data, array $lignes, array $paiement): array
    {
        return DB::transaction(function () use ($data, $lignes, $paiement) {
            $invoice = Invoice::create([
                'client_id' => $data['client_id'] ?? null,
                'date_facture' => $data['date_facture'],
                'numero' => $data['numero'],
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
            ]);

            foreach ($lignes as $line) {
                $invoice->items()->create($this->ligne($line));
            }

            // Lines are written, so the total is only known now.
            $invoice->refresh();
            $invoice->recalcTotals();
            $invoice->refresh();

            $payment = null;

            if ($paiement['encaisser'] && $invoice->total_ttc > 0) {
                // Blank means "the client paid it all"; anything else is a deposit.
                $montant = $paiement['montant'] === null || $paiement['montant'] === ''
                    ? (float) $invoice->total_ttc
                    : (float) $paiement['montant'];

                $montant = min(max($montant, 0), (float) $invoice->total_ttc);

                if ($montant > 0) {
                    $payment = Payment::create([
                        'invoice_id' => $invoice->id,
                        'client_id' => $invoice->client_id,
                        'user_id' => auth()->id(),
                        'date_paiement' => now(),
                        'montant' => $montant,
                        'mode' => $paiement['mode'],
                    ]);
                    $invoice->refresh();
                }
            }

            return [$invoice, $payment];
        });
    }

    /**
     * @param  array{client_id?: ?int, date_facture: string, numero: string, note?: ?string}  $data
     * @param  array<int, array<string, mixed>>  $lignes  a line carrying an `id` is an existing one
     */
    public function modifier(Invoice $invoice, array $data, array $lignes): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $lignes) {
            $invoice->update([
                'client_id' => $data['client_id'] ?? null,
                'date_facture' => $data['date_facture'],
                'numero' => $data['numero'],
                'note' => $data['note'] ?? null,
            ]);

            $kept = collect($lignes)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            // Removed lines first, one by one, so the stock comes back.
            $invoice->items()->whereNotIn('id', $kept)->get()->each->delete();

            foreach ($lignes as $line) {
                $id = isset($line['id']) ? (int) $line['id'] : null;
                $item = $id ? $invoice->items()->whereKey($id)->first() : null;

                $item
                    ? $item->update($this->ligne($line))
                    : $invoice->items()->create($this->ligne($line));
            }

            $invoice->refresh()->recalcTotals();

            return $invoice->refresh();
        });
    }

    /** The "Encaisser" action: one payment against the invoice. */
    public function encaisser(Invoice $invoice, float $montant, string $mode): Payment
    {
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'user_id' => auth()->id(),
            'date_paiement' => now(),
            'montant' => $montant,
            'mode' => $mode,
        ]);

        return $payment;
    }

    /** @return array<string, mixed> the columns the repeater saved */
    private function ligne(array $line): array
    {
        return [
            'article_id' => $line['article_id'] ?? null,
            'designation' => $line['designation'],
            'quantite' => (float) $line['quantite'],
            'prix_unitaire' => (float) $line['prix_unitaire'],
            'remise' => (float) ($line['remise'] ?? 0),
            'tva' => (float) ($line['tva'] ?? 0),
        ];
    }
}
