<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

class TopClientsChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('app.chart.top_clients');
    }

    protected function getData(): array
    {
        $top = Invoice::query()
            
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->selectRaw('clients.raison_sociale as nom, SUM(invoices.total_ttc) as t')
            ->groupBy('clients.id', 'clients.raison_sociale')
            ->orderByDesc('t')
            ->limit(5)
            ->get();

        return [
            'datasets' => [[
                'label' => __('app.invoice.total_ttc'),
                'data' => $top->map(fn ($r) => (float) $r->t)->all(),
                'backgroundColor' => '#10b981',
            ]],
            'labels' => $top->pluck('nom')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
