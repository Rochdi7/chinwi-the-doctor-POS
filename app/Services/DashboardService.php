<?php

namespace App\Services;

use App\Models\Caisse;
use App\Models\CaisseMouvement;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard figures, moved out of the Filament widgets (StatsOverview,
 * CaMensuelChart, StatutsChart, TopClientsChart) with the same queries.
 *
 * An optional period [du, au] narrows invoices by date_facture and payments
 * by date_paiement. Without one, every figure is all-time, as before. The
 * drawer balance is always the balance now.
 */
class DashboardService
{
    /** Rows returned by a detail list; totals still cover every row. */
    public const DETAIL_LIMIT = 100;

    public function __construct(
        private readonly ?string $du = null,
        private readonly ?string $au = null,
    ) {}

    /** @return array{ca: float, regle: float, impaye: float, caisse: float} */
    public function stats(): array
    {
        return [
            'ca' => (float) $this->invoices()->sum('total_ttc'),
            'regle' => (float) $this->payments()->sum('montant'),
            'impaye' => $this->impaye(),
            'caisse' => Caisse::solde(),
        ];
    }

    /**
     * Sum of what each invoice still owes, computed per invoice in SQL (see
     * StatsOverview::impaye): payments not tied to an invoice, or an
     * overpayment, must not mask a real unpaid invoice.
     */
    public function impaye(): float
    {
        return (float) $this->impayeQuery()
            ->selectRaw('SUM(GREATEST(invoices.total_ttc - COALESCE(p.s, 0), 0)) as total')
            ->value('total');
    }

    /**
     * Invoiced and collected per month over the last 12 months (or over the
     * period's months when one is given, at most 24).
     *
     * @return array{labels: array<int, string>, facture: array<int, float>, encaisse: array<int, float>}
     */
    public function mensuel(): array
    {
        $end = $this->au ? Carbon::parse($this->au)->endOfMonth() : now()->endOfMonth();
        $start = $this->du ? Carbon::parse($this->du)->startOfMonth() : $end->copy()->startOfMonth()->subMonths(11);

        if ($start->diffInMonths($end) > 23) {
            $start = $end->copy()->startOfMonth()->subMonths(23);
        }

        $months = collect();
        for ($m = $start->copy(); $m <= $end; $m->addMonth()) {
            $months[$m->format('Y-m')] = 0.0;
        }

        $facture = Invoice::query()
            ->whereBetween('date_facture', [$start->toDateString(), $end->toDateString()])
            ->selectRaw($this->monthExpr('date_facture').' as m, SUM(total_ttc) as t')
            ->groupBy('m')
            ->pluck('t', 'm');

        $encaisse = Payment::query()
            ->whereBetween('date_paiement', [$start->toDateString(), $end->toDateString()])
            ->selectRaw($this->monthExpr('date_paiement').' as m, SUM(montant) as t')
            ->groupBy('m')
            ->pluck('t', 'm');

        return [
            'labels' => $months->keys()->map(fn (string $m) => substr($m, 5, 2).'/'.substr($m, 0, 4))->all(),
            'facture' => $this->bucket($months, $facture),
            'encaisse' => $this->bucket($months, $encaisse),
        ];
    }

    /** @return array<int, array{statut: string, count: int}> in lifecycle order */
    public function statuts(): array
    {
        $counts = $this->invoices()
            ->selectRaw('statut, COUNT(*) as c')
            ->groupBy('statut')
            ->pluck('c', 'statut');

        return collect(Invoice::STATUTS)
            ->filter(fn (string $s) => ($counts[$s] ?? 0) > 0)
            ->map(fn (string $s) => ['statut' => $s, 'count' => (int) $counts[$s]])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, nom: string, total: float}> */
    public function topClients(int $limit = 5): array
    {
        return $this->invoices()
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->selectRaw('clients.id as id, clients.raison_sociale as nom, SUM(invoices.total_ttc) as t')
            ->groupBy('clients.id', 'clients.raison_sociale')
            ->orderByDesc('t')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'nom' => $r->nom, 'total' => (float) $r->t])
            ->all();
    }

    /**
     * The rows behind one card: ca | impaye | regle | caisse.
     *
     * @return array{total: float, count: int, rows: array<int, array<string, mixed>>}
     */
    public function detail(string $key): array
    {
        return match ($key) {
            'ca' => $this->invoiceRows(false),
            'impaye' => $this->invoiceRows(true),
            'regle' => $this->paymentRows(),
            'caisse' => $this->caisseRows(),
        };
    }

    /** @return array{total: float, count: int, rows: array<int, array<string, mixed>>} */
    private function invoiceRows(bool $impaye): array
    {
        $query = $impaye
            ? Invoice::query()->whereIn('invoices.id', $this->impayeQuery()->select('invoices.id')->whereRaw('(invoices.total_ttc - COALESCE(p.s, 0)) > 0.005'))
            : $this->invoices();

        $total = $impaye ? $this->impaye() : (float) (clone $query)->sum('total_ttc');
        $count = (clone $query)->count();

        $rows = $query->with('client')->withSum('payments', 'montant')
            ->orderByDesc('date_facture')->orderByDesc('id')
            ->limit(self::DETAIL_LIMIT)->get()
            ->map(function (Invoice $i) {
                $paye = (float) ($i->payments_sum_montant ?? 0);

                return [
                    'id' => $i->id,
                    'numero' => $i->numero,
                    'date' => $i->date_facture?->toDateString(),
                    'client' => $i->client?->raison_sociale,
                    'statut' => $i->statut,
                    'total_ttc' => (float) $i->total_ttc,
                    'paye' => $paye,
                    'reste' => (float) $i->total_ttc - $paye,
                ];
            })->all();

        return ['total' => $total, 'count' => $count, 'rows' => $rows];
    }

    /** @return array{total: float, count: int, rows: array<int, array<string, mixed>>} */
    private function paymentRows(): array
    {
        $query = $this->payments();

        return [
            'total' => (float) (clone $query)->sum('montant'),
            'count' => (clone $query)->count(),
            'rows' => $query->with(['client', 'invoice'])
                ->orderByDesc('date_paiement')->orderByDesc('id')
                ->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'date' => $p->date_paiement?->toDateString(),
                    'invoice_id' => $p->invoice_id,
                    'numero' => $p->invoice?->numero,
                    'client' => $p->client?->raison_sociale,
                    'mode' => $p->mode,
                    'reference' => $p->reference,
                    'montant' => (float) $p->montant,
                ])->all(),
        ];
    }

    /** @return array{total: float, count: int, rows: array<int, array<string, mixed>>} */
    private function caisseRows(): array
    {
        return [
            'total' => Caisse::solde(),
            'count' => CaisseMouvement::count(),
            'rows' => CaisseMouvement::with('user')
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (CaisseMouvement $m) => [
                    'id' => $m->id,
                    'occurred_at' => $m->occurred_at?->format('Y-m-d H:i:s.v'),
                    'type' => $m->type,
                    'motif' => $m->motif,
                    'user' => $m->user?->name,
                    'solde_avant' => (float) $m->solde_avant,
                    'montant' => (float) $m->montant,
                    'solde_apres' => (float) $m->solde_apres,
                ])->all(),
        ];
    }

    private function invoices(): Builder
    {
        return Invoice::query()
            ->when($this->du, fn ($q, $d) => $q->whereDate('date_facture', '>=', $d))
            ->when($this->au, fn ($q, $d) => $q->whereDate('date_facture', '<=', $d));
    }

    private function payments(): Builder
    {
        return Payment::query()
            ->when($this->du, fn ($q, $d) => $q->whereDate('date_paiement', '>=', $d))
            ->when($this->au, fn ($q, $d) => $q->whereDate('date_paiement', '<=', $d));
    }

    /** Invoices joined to one grouped pass over their payments. */
    private function impayeQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('invoices')
            ->leftJoinSub(
                DB::table('payments')->selectRaw('invoice_id, SUM(montant) as s')->groupBy('invoice_id'),
                'p',
                'p.invoice_id',
                '=',
                'invoices.id',
            )
            ->when($this->du, fn ($q, $d) => $q->whereDate('invoices.date_facture', '>=', $d))
            ->when($this->au, fn ($q, $d) => $q->whereDate('invoices.date_facture', '<=', $d));
    }

    private function monthExpr(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /** Totals folded onto fixed month buckets (see CaMensuelChart::bucket). */
    private function bucket(Collection $months, Collection $totals): array
    {
        return $months->map(fn (float $zero, string $m) => (float) ($totals[$m] ?? $zero))->values()->all();
    }
}
