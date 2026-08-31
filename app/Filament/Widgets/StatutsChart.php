<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

class StatutsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('app.chart.statuts');
    }

    protected function getData(): array
    {
        $colors = [
            'validee' => '#3b82f6',
            'partielle' => '#f59e0b',
            'payee' => '#10b981',
        ];

        $counts = Invoice::query()
            ->selectRaw('statut, COUNT(*) as c')
            ->groupBy('statut')
            ->pluck('c', 'statut');

        // Keep a stable slice order regardless of what the DB returns, but
        // still show any statut missing from the map above (a new enum value)
        // rather than dropping those invoices off the chart entirely.
        $ordered = collect($colors)->keys()
            ->merge($counts->keys()->diff(array_keys($colors)))
            ->filter(fn (string $statut) => ($counts[$statut] ?? 0) > 0)
            ->values();

        return [
            'datasets' => [[
                'label' => __('app.invoice.plural'),
                'data' => $ordered->map(fn ($s) => (int) $counts[$s])->all(),
                'backgroundColor' => $ordered->map(fn ($s) => $colors[$s] ?? '#6b7280')->all(),
            ]],
            'labels' => $ordered->map(fn ($s) => __('app.statut.'.$s))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
