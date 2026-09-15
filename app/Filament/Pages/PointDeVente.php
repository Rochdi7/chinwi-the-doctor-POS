<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use App\Support\ScanCart;
use Filament\Actions\Action;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * The till screen: a product grid on one side, the live cart on the other.
 *
 * Everything the cashier does lands in the cart without a page change: a
 * scan, a click on a product tile, a +/- on a line. "Encaisser" writes the
 * sale, the payment and the drawer movement in one go through the same
 * observers the Ventes form uses, so stock and audit stay consistent.
 *
 * The grid itself is App\Livewire\PosGrille, a child component: Livewire
 * leaves it alone when this page re-renders, so a scan only ships the cart.
 */
class PointDeVente extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'pos';

    protected static string $view = 'filament.pages.point-de-vente';

    public string $scan = '';

    public ?int $client_id = null;

    public string $mode = 'especes';

    public string $montant_recu = '';

    /**
     * Same shape as the Ventes repeater rows so ScanCart can be reused as is.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $panier = [];

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.vente');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.pos.label');
    }

    public function getTitle(): string
    {
        return __('app.pos.label');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ventes')
                ->label(__('app.pos.ventes'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(InvoiceResource::getUrl('index')),
        ];
    }

    // ---- What the side panel reads (the grid is App\Livewire\PosGrille) ----

    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->orderBy('raison_sociale')->limit(200)->get(['id', 'raison_sociale']);
    }

    /** @return array{ht: float, tva: float, ttc: float} */
    public function totaux(): array
    {
        $ht = 0.0;
        $ttc = 0.0;

        foreach ($this->panier as $line) {
            $ligneHt = max(
                ((float) ($line['quantite'] ?? 0) * (float) ($line['prix_unitaire'] ?? 0))
                - (float) ($line['remise'] ?? 0),
                0
            );
            $ht += $ligneHt;
            $ttc += $ligneHt * (1 + (float) ($line['tva'] ?? 0) / 100);
        }

        return ['ht' => round($ht, 2), 'tva' => round($ttc - $ht, 2), 'ttc' => round($ttc, 2)];
    }

    public function ligneTotal(array $line): float
    {
        $ht = max(
            ((float) ($line['quantite'] ?? 0) * (float) ($line['prix_unitaire'] ?? 0))
            - (float) ($line['remise'] ?? 0),
            0
        );

        return round($ht * (1 + (float) ($line['tva'] ?? 0) / 100), 2);
    }

    /** Blank "montant reçu" means the client pays everything. */
    public function montantEncaisse(): float
    {
        $ttc = $this->totaux()['ttc'];

        if (trim($this->montant_recu) === '') {
            return $ttc;
        }

        return max((float) str_replace(',', '.', $this->montant_recu), 0);
    }

    /** Cash handed back when the client gives more than the total. */
    public function monnaie(): float
    {
        return round(max($this->montantEncaisse() - $this->totaux()['ttc'], 0), 2);
    }

    /** What is still owed when the client gives less than the total. */
    public function reste(): float
    {
        return round(max($this->totaux()['ttc'] - $this->montantEncaisse(), 0), 2);
    }

    // ---- Putting things in the cart ----------------------------------------

    /**
     * A scan. The page passes the code it caught (from whichever field the
     * scanner typed into); without one, the scan box was submitted by hand.
     */
    public function scanner(?string $code = null): void
    {
        $code = trim($code ?? $this->scan);
        // Always clear: the next scan must land in an empty box, hit or miss.
        $this->scan = '';

        if ($code === '') {
            return;
        }

        $article = Article::findByScan($code);

        if (! $article) {
            Notification::make()
                ->title(__('app.scan.introuvable', ['code' => $code]))
                ->danger()
                ->send();

            return;
        }

        $this->mettreAuPanier($article, $code);
    }

    /** A tap on a product tile. */
    public function ajouter(int $articleId): void
    {
        $article = Article::query()->where('actif', true)->find($articleId);

        if (! $article) {
            return;
        }

        $this->mettreAuPanier($article, (string) ($article->code_barre ?? $article->reference));
    }

    private function mettreAuPanier(Article $article, string $code): void
    {
        $result = ScanCart::add(ScanCart::withoutBlankRows($this->panier), $code, $article);
        $this->panier = $result['items'];

        // Selling into negative stock is legitimate (loose weight, a delivery
        // not yet keyed in): warn, never block the counter.
        if ((float) $article->stock <= 0) {
            Notification::make()
                ->title(__('app.scan.stock_zero', ['article' => $article->designation]))
                ->warning()
                ->send();
        }
    }

    public function plus(string $key): void
    {
        if (isset($this->panier[$key])) {
            $this->panier[$key]['quantite'] = (float) $this->panier[$key]['quantite'] + 1;
        }
    }

    public function moins(string $key): void
    {
        if (! isset($this->panier[$key])) {
            return;
        }

        $qty = (float) $this->panier[$key]['quantite'] - 1;

        // Going below one is the same as removing the line.
        if ($qty <= 0) {
            unset($this->panier[$key]);

            return;
        }

        $this->panier[$key]['quantite'] = $qty;
    }

    /** Typed straight into the quantity box (loose weight, a box of 12). */
    public function quantite(string $key, $valeur): void
    {
        if (! isset($this->panier[$key])) {
            return;
        }

        $qty = (float) str_replace(',', '.', (string) $valeur);

        if ($qty <= 0) {
            unset($this->panier[$key]);

            return;
        }

        $this->panier[$key]['quantite'] = $qty;
    }

    public function retirer(string $key): void
    {
        unset($this->panier[$key]);
    }

    public function vider(): void
    {
        $this->panier = [];
        $this->montant_recu = '';
    }

    // ---- Saving the sale ---------------------------------------------------

    /** Save and take the money. */
    public function encaisser(): void
    {
        $this->enregistrerVente(true);
    }

    /** Save on credit: the client settles later from the Ventes list. */
    public function enregistrer(): void
    {
        $this->enregistrerVente(false);
    }

    private function enregistrerVente(bool $encaisser): void
    {
        $lignes = ScanCart::withoutBlankRows($this->panier);

        if ($lignes === []) {
            Notification::make()
                ->title(__('app.pos.panier_vide_erreur'))
                ->danger()
                ->send();

            return;
        }

        $montant = $this->montantEncaisse();
        $monnaie = $this->monnaie();

        [$invoice, $payment] = DB::transaction(function () use ($lignes, $encaisser, $montant) {
            $invoice = Invoice::create([
                'numero' => Invoice::nextNumero(),
                'date_facture' => now()->toDateString(),
                'client_id' => $this->client_id,
                'user_id' => auth()->id(),
            ]);

            // Each create() runs the InvoiceItemObserver: line totals, stock
            // movement and audit trail happen there, exactly as in Ventes.
            foreach ($lignes as $line) {
                $invoice->items()->create([
                    'article_id' => $line['article_id'],
                    'designation' => $line['designation'],
                    'quantite' => (float) $line['quantite'],
                    'prix_unitaire' => (float) $line['prix_unitaire'],
                    'remise' => (float) ($line['remise'] ?? 0),
                    'tva' => (float) ($line['tva'] ?? 0),
                ]);
            }

            $invoice->recalcTotals();
            $invoice->refresh();

            $payment = null;
            $montant = min($montant, (float) $invoice->total_ttc);

            if ($encaisser && $montant > 0) {
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'user_id' => auth()->id(),
                    'date_paiement' => now(),
                    'montant' => $montant,
                    'mode' => $this->mode,
                ]);
            }

            return [$invoice, $payment];
        });

        Notification::make()
            ->title(__('app.pos.vente_ok', ['numero' => $invoice->numero]))
            ->body($encaisser && $monnaie > 0 ? __('app.pos.monnaie').' : '.Money::format($monnaie) : null)
            ->success()
            ->persistent()
            ->actions(array_values(array_filter([
                $payment ? NotificationAction::make('recu')
                    ->label(__('app.receipt.print'))
                    ->url(route('payment.pdf', $payment))
                    ->openUrlInNewTab()
                    ->button() : null,
                NotificationAction::make('vente')
                    ->label(__('app.invoice.print'))
                    ->url(route('invoice.pdf', $invoice))
                    ->openUrlInNewTab(),
            ])))
            ->send();

        // Ready for the next customer.
        $this->panier = [];
        $this->montant_recu = '';
        $this->client_id = null;
        $this->scan = '';
    }
}
