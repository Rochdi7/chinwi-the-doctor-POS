<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CaMensuelChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('app.chart.ca_mensuel');
    }

    /**
     * Month bucket expression: MySQL and SQLite spell this differently, and
     * the test suite runs on SQLite while production is MySQL.
     */
    protected function monthExpr(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * Fold DB totals onto the fixed month buckets.
     *
     * Only known buckets are filled: merge() would APPEND an out-of-window key
     * (a future-dated or mis-keyed row), leaving more data points than labels
     * and silently shifting the whole series in Chart.js.
     */
    protected function bucket(\Illuminate\Support\Collection $months, \Illuminate\Support\Collection $totals): array
    {
        return $months
            ->map(fn (float $zero, string $m) => (float) ($totals[$m] ?? $zero))
            ->values()
            ->all();
    }

    protected function getData(): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $end = now()->endOfMonth();

        // Keys are Y-m so the two series line up on the same buckets.
        $months = collect(range(0, 11))
            ->mapWithKeys(fn (int $i) => [$start->copy()->addMonths($i)->format('Y-m') => 0.0]);

        $facture = $this->bucket($months, Invoice::query()
            
            ->whereBetween('date_facture', [$start->toDateString(), $end->toDateString()])
            ->selectRaw($this->monthExpr('date_facture').' as m, SUM(total_ttc) as t')
            ->groupBy('m')
            ->pluck('t', 'm'));

        $encaisse = $this->bucket($months, Payment::query()
            ->whereBetween('date_paiement', [$start->toDateString(), $end->toDateString()])
            ->selectRaw($this->monthExpr('date_paiement').' as m, SUM(montant) as t')
            ->groupBy('m')
            ->pluck('t', 'm'));

        return [
            'datasets' => [
                [
                    'label' => __('app.chart.facture'),
                    'data' => $facture,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => __('app.chart.encaisse'),
                    'data' => $encaisse,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            // Format from the key itself: createFromFormat('Y-m') would inherit
            // today's day-of-month and overflow short months (Feb 30 -> Mar).
            'labels' => $months->keys()
                ->map(fn (string $m) => substr($m, 5, 2).'/'.substr($m, 0, 4))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
