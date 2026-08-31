<?php

namespace App\Filament\Resources\CaisseMouvementResource\Pages;

use App\Filament\Resources\CaisseMouvementResource;
use App\Models\Caisse;
use Filament\Resources\Pages\ListRecords;

class ListCaisseMouvements extends ListRecords
{
    protected static string $resource = CaisseMouvementResource::class;

    public function getSubheading(): string
    {
        return __('app.caisse.solde').' : '.number_format(Caisse::solde(), 2).' DH';
    }

    /**
     * No manual entrée/sortie here on purpose. The caisse must only ever move
     * as the mirror of a real payment (see PaymentObserver): a hand-typed
     * amount lets the till be adjusted to match a miscount instead of the
     * miscount being explained, and the journal loses its value as evidence.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
