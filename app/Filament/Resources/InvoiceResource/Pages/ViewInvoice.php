<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return __('app.invoice.label').' '.$this->record->numero;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label(__('app.action.modifier'))
                ->icon('heroicon-o-pencil-square'),

            Actions\Action::make('print')
                ->label(__('app.invoice.print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn (Invoice $record) => route('invoice.pdf', $record))
                ->openUrlInNewTab(),
        ];
    }
}
