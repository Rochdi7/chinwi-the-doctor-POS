<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    /**
     * Offer the receipt straight away: the client is still at the counter.
     */
    protected function afterCreate(): void
    {
        Notification::make()
            ->title(__('app.receipt.print'))
            ->success()
            ->actions([
                Action::make('print')
                    ->label(__('app.receipt.print'))
                    ->url(route('payment.pdf', $this->record))
                    ->openUrlInNewTab()
                    ->button(),
            ])
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
