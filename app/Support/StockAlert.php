<?php

namespace App\Support;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

class StockAlert
{
    /**
     * Warn the counter as soon as a sale empties an article, with a link
     * straight to the edit screen so it can be restocked on the spot.
     */
    public static function check(?int $articleId): void
    {
        if (! $articleId) {
            return;
        }

        $article = Article::find($articleId);

        if (! $article || (float) $article->stock > 0) {
            return;
        }

        $negative = (float) $article->stock < 0;

        Notification::make()
            ->title(__($negative ? 'app.alert.stock_bas_titre' : 'app.alert.stock_zero_titre'))
            ->body(__(
                $negative ? 'app.alert.stock_bas_corps' : 'app.alert.stock_zero_corps',
                ['article' => $article->designation, 'stock' => (float) $article->stock],
            ))
            ->icon('heroicon-o-exclamation-triangle')
            ->color($negative ? 'danger' : 'warning')
            ->danger()
            ->persistent()
            ->actions([
                Action::make('restock')
                    ->label(__('app.alert.reapprovisionner'))
                    ->url(ArticleResource::getUrl('edit', ['record' => $article]))
                    ->button(),
            ])
            ->send();
    }
}
