<?php

namespace App\Observers;

use App\Models\Caisse;
use App\Models\Client;
use App\Models\Invoice;
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

        // The drawer holds what the payment said before the edit: take that
        // cash back out and put the new cash in, so a later delete reverses
        // exactly what is there.
        if ($payment->wasChanged(['montant', 'mode'])) {
            $oldMode = $payment->getOriginal('mode');
            $oldMontant = (float) $payment->getOriginal('montant');

            if ($oldMode === 'especes') {
                Caisse::mouvement('sortie', $oldMontant, __('app.caisse.annulation_reglement', ['id' => $payment->id]));
            }
            if ($payment->mode === 'especes') {
                Caisse::mouvement(
                    'entree',
                    (float) $payment->montant,
                    __('app.payment.label').': '.($payment->client?->raison_sociale ?? __('app.vente.client_passage')),
                    $payment->id,
                );
            }
        }

        $payment->invoice?->recalcTotals();
        $payment->client?->recalcSolde();

        // Moved to another invoice or client: the one it left must drop it too.
        if ($payment->wasChanged('invoice_id') && $old = $payment->getOriginal('invoice_id')) {
            Invoice::find($old)?->recalcTotals();
        }
        if ($payment->wasChanged('client_id') && $old = $payment->getOriginal('client_id')) {
            Client::find($old)?->recalcSolde();
        }
    }

    public function deleted(Model $payment): void
    {
        parent::deleted($payment);

        if ($payment->mode === 'especes') {
            Caisse::mouvement(
                'sortie',
                (float) $payment->montant,
                __('app.caisse.annulation_reglement', ['id' => $payment->id]),
            );
        }

        $payment->invoice?->recalcTotals();
        $payment->client?->recalcSolde();
    }
}
