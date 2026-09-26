<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place a till sale is written.
 *
 * Moved as is out of PointDeVente::enregistrerVente() so the Livewire till and
 * the JSON API (used by the React till) share a single implementation. The
 * rules themselves stay where they were: line totals in InvoiceItem, invoice
 * totals and status in Invoice, stock in InvoiceItemObserver, the drawer in
 * PaymentObserver/Caisse, the audit trail in AuditObserver.
 */
class SaleService
{
    /**
     * Save a sale and, when $encaisser, the payment that settles it.
     *
     * @param  array<int|string, array{article_id: ?int, designation: string, quantite: mixed, prix_unitaire: mixed, remise?: mixed, tva?: mixed}>  $lignes
     * @param  float|null  $montant  what the client hands over; null means
     *                               "pays everything" (the invoice total)
     * @return array{0: Invoice, 1: Payment|null}
     */
    public function enregistrer(array $lignes, ?int $clientId, bool $encaisser, ?float $montant, string $mode): array
    {
        if ($lignes === []) {
            throw ValidationException::withMessages(['items' => __('app.pos.panier_vide_erreur')]);
        }

        // Two tills (often the same account on two devices) can save at the
        // same instant, and both would read the same "next" number. Sales
        // are numbered one at a time: the second waits for the first to
        // commit. Should the unique index still refuse one (a number just
        // typed in the back office), its transaction is rolled back whole
        // (lines, stock, drawer, audit) and saved again.
        return $this->unParUn(function () use ($lignes, $clientId, $encaisser, $montant, $mode) {
            for ($attempt = 1; ; $attempt++) {
                try {
                    return $this->ecrire($lignes, $clientId, $encaisser, $montant, $mode);
                } catch (UniqueConstraintViolationException $e) {
                    if ($attempt >= self::TENTATIVES || ! str_contains($e->getMessage(), 'numero')) {
                        throw $e;
                    }
                }
            }
        });
    }

    /** Attempts before a numbering collision is reported as an error. */
    private const TENTATIVES = 5;

    /** How long a till waits for another till's sale to be written. */
    private const ATTENTE_SECONDES = 20;

    /**
     * Run $write holding the database's named lock for invoice numbering.
     * A MySQL/MariaDB lock, not the cache: waiters queue in the server
     * instead of polling, and it is released the moment $write returns
     * (after its commit) or the connection drops.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function unParUn(callable $write): mixed
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $write();
        }

        if ((int) DB::scalar('SELECT GET_LOCK(?, ?)', ['invoice-numero', self::ATTENTE_SECONDES]) !== 1) {
            throw new \RuntimeException('Invoice numbering is busy.');
        }

        try {
            return $write();
        } finally {
            DB::statement('DO RELEASE_LOCK(?)', ['invoice-numero']);
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $lignes
     * @return array{0: Invoice, 1: Payment|null}
     */
    private function ecrire(array $lignes, ?int $clientId, bool $encaisser, ?float $montant, string $mode): array
    {
        return DB::transaction(function () use ($lignes, $clientId, $encaisser, $montant, $mode) {
            $invoice = Invoice::create([
                'numero' => Invoice::nextNumero(),
                'date_facture' => now()->toDateString(),
                'client_id' => $clientId,
                'user_id' => auth()->id(),
            ]);

            // Each create() runs the InvoiceItemObserver: line totals, stock
            // movement and audit trail happen there, exactly as in Ventes.
            foreach ($lignes as $line) {
                $invoice->items()->create([
                    'article_id' => $line['article_id'],
                    'designation' => $line['designation'],
                    'quantite' => (float) $line['quantite'],
                    'prix_unitaire' => (float) $line['prix_unitaire'],
                    'remise' => (float) ($line['remise'] ?? 0),
                    'tva' => (float) ($line['tva'] ?? 0),
                ]);
            }

            $invoice->recalcTotals();
            $invoice->refresh();

            $payment = null;
            $montant = min($montant ?? (float) $invoice->total_ttc, (float) $invoice->total_ttc);

            if ($encaisser && $montant > 0) {
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'user_id' => auth()->id(),
                    'date_paiement' => now(),
                    'montant' => $montant,
                    'mode' => $mode,
                ]);

                // PaymentObserver recalculated montant_paye and statut in the
                // database; reload so callers see them.
                $invoice->refresh();
            }

            return [$invoice, $payment];
        }, attempts: 3); // a deadlock between two tills is retried by Laravel
    }

    /**
     * Sale lines built from article ids, the way a scan or a tile tap builds
     * them (ScanCart::add): name, price and VAT always come from the article,
     * never from the client.
     *
     * @param  array<int, array{article_id: int, quantite: mixed}>  $items
     * @return array<int, array{article_id: int, designation: string, quantite: float, prix_unitaire: float, remise: float, tva: float}>
     */
    public function lignesDepuisArticles(array $items): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (int) $i['article_id'], $items)));
        $articles = Article::query()->where('actif', true)->whereKey($ids)->get()->keyBy('id');

        $lignes = [];

        foreach ($items as $i => $item) {
            $article = $articles->get((int) $item['article_id']);

            if (! $article) {
                throw ValidationException::withMessages([
                    "items.$i.article_id" => __('app.scan.introuvable', ['code' => $item['article_id']]),
                ]);
            }

            $lignes[] = [
                'article_id' => $article->id,
                'designation' => $article->designation,
                'quantite' => (float) $item['quantite'],
                'prix_unitaire' => (float) $article->prix_vente,
                'remise' => 0.0,
                'tva' => (float) $article->tva,
            ];
        }

        return $lignes;
    }

    /**
     * What the till shows before saving, computed with the rule that will
     * be stored: each line through InvoiceItem::computeTotals(), the
     * invoice as the sum of its lines (Invoice::recalcTotals()).
     *
     * @param  array<int, array{article_id: int, designation: string, quantite: float, prix_unitaire: float, remise: float, tva: float}>  $lignes
     * @return array{lignes: array<int, array<string, mixed>>, total_ht: float, total_tva: float, total_ttc: float}
     */
    public function apercu(array $lignes): array
    {
        $ht = 0.0;
        $ttc = 0.0;
        $out = [];

        foreach ($lignes as $line) {
            $item = new InvoiceItem([
                'quantite' => $line['quantite'],
                'prix_unitaire' => $line['prix_unitaire'],
                'remise' => $line['remise'] ?? 0,
                'tva' => $line['tva'] ?? 0,
            ]);
            $item->computeTotals();

            $ht += (float) $item->total_ht;
            $ttc += (float) $item->total_ttc;

            $out[] = $line + [
                'total_ht' => (float) $item->total_ht,
                'total_ttc' => (float) $item->total_ttc,
            ];
        }

        return [
            'lignes' => $out,
            'total_ht' => round($ht, 2),
            'total_tva' => round($ttc - $ht, 2),
            'total_ttc' => round($ttc, 2),
        ];
    }

    /**
     * Change and balance for an amount handed over, as the till shows them:
     * blank means the client pays everything.
     *
     * @return array{montant_encaisse: float, monnaie: float, reste: float}
     */
    public function rendu(float $ttc, ?float $recu): array
    {
        $encaisse = $recu === null ? $ttc : max($recu, 0);

        return [
            'montant_encaisse' => round(min($encaisse, $ttc), 2),
            'monnaie' => round(max($encaisse - $ttc, 0), 2),
            'reste' => round(max($ttc - $encaisse, 0), 2),
        ];
    }
}
