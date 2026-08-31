<?php

namespace App\Filament\Widgets;

use App\Models\Caisse;
use App\Models\CaisseMouvement;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class StatsOverview extends Widget
{
    protected static ?int $sort = 1;

    /** Rows rendered in a detail modal. Footer totals still cover every row. */
    public const DETAIL_LIMIT = 100;

    protected static string $view = 'filament.widgets.stats-overview';

    protected int|string|array $columnSpan = 'full';

    /** Which card's detail modal is open: ca|regle|impaye|caisse|null */
    public ?string $detail = null;

    public function openDetail(string $key): void
    {
        $this->detail = $key;
        $this->dispatch('open-modal', id: 'stats-detail');
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }

    protected function ca(): float
    {
        return (float) Invoice::sum('total_ttc');
    }

    protected function regle(): float
    {
        return (float) Payment::sum('montant');
    }

    /**
     * Sum of what each live invoice still owes. Computed per invoice rather
     * than as CA - reglé, because payments not tied to an invoice (or an
     * overpayment) would otherwise mask real unpaid invoices.
     *
     * Done in SQL: hydrating every invoice to add them up in PHP cost ~220ms
     * and 70MB at 5k invoices, and grew with the table. GREATEST(...) applies
     * the same per-invoice floor of 0 that the PHP max() did.
     */
    protected function impaye(): float
    {
        return (float) DB::table('invoices')
            ->leftJoinSub(
                DB::table('payments')->selectRaw('invoice_id, SUM(montant) as s')->groupBy('invoice_id'),
                'p',
                'p.invoice_id',
                '=',
                'invoices.id',
            )
            ->selectRaw('SUM(GREATEST(invoices.total_ttc - COALESCE(p.s, 0), 0)) as total')
            ->value('total');
    }

    public function getStatsProperty(): array
    {
        $ca = $this->ca();
        $regle = $this->regle();

        return [
            'ca' => [
                'label' => __('app.stats.ca'),
                'value' => $ca,
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'info',
            ],
            'regle' => [
                'label' => __('app.stats.regle'),
                'value' => $regle,
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
            ],
            'impaye' => [
                'label' => __('app.stats.impaye'),
                'value' => $this->impaye(),
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'danger',
            ],
            'caisse' => [
                'label' => __('app.stats.caisse'),
                'value' => Caisse::solde(),
                'icon' => 'heroicon-o-wallet',
                'color' => 'warning',
            ],
        ];
    }

    /**
     * Rows behind the clicked figure: a heading, the columns, and the lines
     * that add up to exactly the number shown on the card.
     */
    public function getDetailRowsProperty(): array
    {
        return match ($this->detail) {
            'ca' => $this->invoiceRows(Invoice::query()),
            'impaye' => $this->invoiceRows(
                Invoice::query(),
                impaye: true,
            ),
            'regle' => $this->paymentRows(),
            'caisse' => $this->caisseRows(),
            default => ['columns' => [], 'rows' => [], 'total' => 0, 'count' => 0],
        };
    }

    protected function invoiceRows($query, bool $impaye = false): array
    {
        // The "still owes money" filter runs in SQL, not on the hydrated slice:
        // filtering afterwards would leave the row count wrong and could fill
        // the preview entirely with already-paid invoices.
        if ($impaye) {
            // Joined against one grouped pass over payments rather than a
            // correlated subquery per invoice: 4x faster at 60k invoices.
            $query->leftJoinSub(
                DB::table('payments')->selectRaw('invoice_id, SUM(montant) as s')->groupBy('invoice_id'),
                'p',
                'p.invoice_id',
                '=',
                'invoices.id',
            )
                ->select('invoices.*')
                ->whereRaw('(invoices.total_ttc - COALESCE(p.s, 0)) > 0.005');
        }

        // Only the most recent slice is hydrated: the modal is a preview, and
        // rendering every invoice cost ~1s and 70MB at 5k rows. The footer
        // total still covers the whole set - it is computed in SQL below.
        $total = $impaye
            ? $this->impaye()
            : (float) (clone $query)->sum('total_ttc');

        $count = (clone $query)->count();

        $invoices = $query->with('client')->withSum('payments', 'montant')
            ->orderByDesc('date_facture')->orderByDesc('id')
            ->limit(self::DETAIL_LIMIT)->get();

        $rows = $invoices->map(function (Invoice $i) use ($impaye) {
            $paye = (float) ($i->payments_sum_montant ?? 0);
            $reste = (float) $i->total_ttc - $paye;

            return [
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $i]),
                'cells' => [
                    $i->numero,
                    $i->date_facture?->format('d/m/Y'),
                    $i->client?->raison_sociale ?? '—',
                    __('app.statut.'.$i->statut),
                    number_format((float) $i->total_ttc, 2).' DH',
                    number_format($paye, 2).' DH',
                    number_format($reste, 2).' DH',
                ],
                'amount' => $impaye ? $reste : (float) $i->total_ttc,
            ];
        })->all();

        return [
            'columns' => [
                __('app.invoice.numero'),
                __('app.invoice.date_facture'),
                __('app.client.label'),
                __('app.invoice.statut'),
                __('app.pdf.total_ttc'),
                __('app.pdf.paye'),
                __('app.pdf.reste'),
            ],
            'rows' => $rows,
            'total' => $total,
            'count' => $count,
        ];
    }

    protected function paymentRows(): array
    {
        $total = (float) Payment::sum('montant');
        $count = Payment::count();

        $payments = Payment::with(['client', 'invoice'])
            ->orderByDesc('date_paiement')->orderByDesc('id')
            ->limit(self::DETAIL_LIMIT)->get();

        $rows = $payments->map(fn (Payment $p) => [
            'url' => \App\Filament\Resources\PaymentResource::getUrl('edit', ['record' => $p]),
            'cells' => [
                $p->date_paiement?->format('d/m/Y'),
                $p->invoice?->numero ?? '—',
                $p->client?->raison_sociale ?? '—',
                __('app.mode.'.$p->mode),
                $p->reference ?: '—',
                number_format((float) $p->montant, 2).' DH',
            ],
            'amount' => (float) $p->montant,
        ])->all();

        return [
            'columns' => [
                __('app.payment.date_paiement'),
                __('app.invoice.numero'),
                __('app.client.label'),
                __('app.payment.mode'),
                __('app.payment.reference'),
                __('app.payment.montant'),
            ],
            'rows' => $rows,
            'total' => $total,
            'count' => $count,
        ];
    }

    protected function caisseRows(): array
    {
        $total = Caisse::solde();
        $count = CaisseMouvement::count();

        $mouvements = CaisseMouvement::with('user')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(self::DETAIL_LIMIT)->get();

        $rows = $mouvements->map(fn (CaisseMouvement $m) => [
            'url' => null,
            'cells' => [
                $m->occurred_at?->format('d/m/Y H:i:s.v'),
                __('app.caisse.'.$m->type),
                $m->motif,
                $m->user?->name ?? '—',
                number_format((float) $m->solde_avant, 2).' DH',
                number_format((float) $m->montant, 2).' DH',
                number_format((float) $m->solde_apres, 2).' DH',
            ],
            'amount' => $m->type === 'entree' ? (float) $m->montant : -(float) $m->montant,
        ])->all();

        return [
            'columns' => [
                __('app.caisse.heure'),
                __('app.caisse.type'),
                __('app.caisse.motif'),
                __('app.log.user'),
                __('app.caisse.solde_avant'),
                __('app.caisse.montant'),
                __('app.caisse.solde_apres'),
            ],
            'rows' => $rows,
            'total' => $total,
            'count' => $count,
        ];
    }
}
