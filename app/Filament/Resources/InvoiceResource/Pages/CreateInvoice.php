<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Payment;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /** Payment fields are not columns on the invoice; kept aside here. */
    private array $paiement = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $state = $this->form->getRawState();

        $this->paiement = [
            'encaisser' => (bool) ($state['encaisser_maintenant'] ?? false),
            'mode' => $state['mode_paiement'] ?? 'especes',
            'montant' => $state['montant_recu'] ?? null,
        ];

        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn () => static::getModel()::create($data));
    }

    protected function afterCreate(): void
    {
        // Lines are written by the repeater, so the total is only known now.
        $invoice = $this->record->refresh();
        $invoice->recalcTotals();
        $invoice->refresh();

        if (! $this->paiement['encaisser'] || $invoice->total_ttc <= 0) {
            return;
        }

        // Blank means "the client paid it all"; anything else is a deposit.
        $montant = $this->paiement['montant'] === null || $this->paiement['montant'] === ''
            ? (float) $invoice->total_ttc
            : (float) $this->paiement['montant'];

        $montant = min(max($montant, 0), (float) $invoice->total_ttc);

        if ($montant <= 0) {
            return;
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'user_id' => auth()->id(),
            'date_paiement' => now(),
            'montant' => $montant,
            'mode' => $this->paiement['mode'],
        ]);

        Notification::make()
            ->title(__('app.vente.paye'))
            ->success()
            ->persistent()
            ->actions([
                Action::make('recu')
                    ->label(__('app.receipt.print'))
                    ->url(route('payment.pdf', $payment))
                    ->openUrlInNewTab()
                    ->button(),
                Action::make('vente')
                    ->label(__('app.invoice.print'))
                    ->url(route('invoice.pdf', $invoice))
                    ->openUrlInNewTab(),
            ])
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
