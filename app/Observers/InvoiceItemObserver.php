<?php

namespace App\Observers;

use App\Models\Article;
use App\Support\StockAlert;
use Illuminate\Database\Eloquent\Model;

class InvoiceItemObserver extends AuditObserver
{
    public function saving(Model $item): void
    {
        $item->computeTotals();
    }

    public function created(Model $item): void
    {
        parent::created($item);
        $this->moveStock($item->article_id, -(float) $item->quantite);
        $item->invoice?->recalcTotals();
    }

    public function updated(Model $item): void
    {
        parent::updated($item);

        // Only a real quantity/article change moves stock. Price, discount and
        // designation edits re-save the row but must never touch the warehouse.
        $qtyChanged = $item->wasChanged('quantite');
        $articleChanged = $item->wasChanged('article_id');

        if ($qtyChanged || $articleChanged) {
            $oldArticle = $item->getOriginal('article_id');
            $oldQty = (float) $item->getOriginal('quantite');

            if ($articleChanged) {
                $this->moveStock($oldArticle, $oldQty);
                $this->moveStock($item->article_id, -(float) $item->quantite);
            } else {
                $this->moveStock($item->article_id, $oldQty - (float) $item->quantite);
            }
        }

        $item->invoice?->recalcTotals();
    }

    public function deleted(Model $item): void
    {
        parent::deleted($item);
        $this->moveStock($item->article_id, (float) $item->quantite);
        $item->invoice?->recalcTotals();
    }

    private function moveStock(?int $articleId, float $delta): void
    {
        if (! $articleId || $delta == 0.0) {
            return;
        }

        // increment() bypasses model events on purpose: an Article "updated"
        // log here would bury the real transaction under stock noise.
        Article::whereKey($articleId)->increment('stock', $delta);

        // Only worth a warning when stock just left the shelf.
        if ($delta < 0) {
            StockAlert::check($articleId);
        }
    }
}
