<?php

namespace App\Observers;

use App\Models\Caisse;
use Illuminate\Database\Eloquent\Model;

class PaymentObserver extends AuditObserver
{
    public function created(Model $payment): void
    {
        parent::created($payment);

        if ($payment->mode === 'especes') {
            Caisse::mouvement(
                'entree',
                (float) $payment->montant,
                // A counter sale has no named buyer; say so rather than
                // printing a dangling id in the cash journal.
                __('app.payment.label').': '.($payment->client?->raison_sociale ?? __('app.vente.client_passage')),
                $payment->id,
            );
        }

        $payment->invoice?->recalcTotals();
        $payment->client?->recalcSolde();
    }

    public function updated(Model $payment): void
    {
        parent::updated($payment);
        $payment->invoice?->recalcTotals();
        $payment->client?->recalcSolde();
    }

    public function deleted(Model $payment): void
    {
        parent::deleted($payment);

        if ($payment->mode === 'especes') {
            Caisse::mouvement(
                'sortie',
                (float) $payment->montant,
                'Annulation règlement #'.$payment->id,
            );
        }

        $payment->invoice?->recalcTotals();
        $payment->client?->recalcSolde();
    }
}
