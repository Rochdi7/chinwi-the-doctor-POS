<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\CategoryResource;
use App\Models\Article;
use App\Models\Setting;
use App\Support\Barcode;
use App\Support\ProductLookup;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajoutRapide(),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Quick add for products that already carry a manufacturer barcode:
     * scan, get the name (from the catalogue or online), type the price,
     * save, scan the next one.
     */
    private function ajoutRapide(): Actions\CreateAction
    {
        return Actions\CreateAction::make('ajoutRapide')
            ->label(__('app.ajout_rapide.label'))
            ->icon('heroicon-o-qr-code')
            ->color('success')
            ->modalHeading(__('app.ajout_rapide.label'))
            ->modalDescription(__('app.ajout_rapide.aide'))
            ->modalWidth('2xl')
            ->createAnother(true)
            ->form([
                Forms\Components\TextInput::make('code_barre')
                    ->label(__('app.article.code_barre'))
                    ->placeholder(__('app.ajout_rapide.scanner'))
                    ->prefixIcon('heroicon-o-qr-code')
                    ->required()
                    ->autofocus()
                    ->unique('articles', 'code_barre')
                    ->live(onBlur: true)
                    // The scanner ends with Enter, which would submit the
                    // modal: turn it into a blur so the code is looked up.
                    ->extraInputAttributes(['x-on:keydown.enter.prevent' => '$el.blur()'])
                    ->dehydrateStateUsing(fn (?string $state) => Barcode::normalizeScan((string) $state))
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        $code = Barcode::normalizeScan((string) $value);

                        if (preg_match('/^\d{13}$/', $code) && ! Barcode::isValidEan13($code)) {
                            $fail(__('app.article.code_barre_checksum'));
                        }
                    })
                    ->afterStateUpdated(fn (?string $state, Forms\Set $set, Forms\Get $get) => $this->scanne((string) $state, $set, $get))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('designation')
                    ->label(__('app.article.designation'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('prix_vente')
                    ->label(__('app.article.prix_vente'))
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->suffix('DH'),
                Forms\Components\TextInput::make('prix_achat')
                    ->label(__('app.article.prix_achat'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('DH'),
                Forms\Components\TextInput::make('stock')
                    ->label(__('app.article.stock'))
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('tva')
                    ->label(__('app.article.tva'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(fn () => Setting::tvaDefaut())
                    ->suffix('%'),
                Forms\Components\TextInput::make('marque')
                    ->label(__('app.article.marque'))
                    ->maxLength(60),
                Forms\Components\Select::make('category_id')
                    ->label(__('app.article.categorie'))
                    ->relationship('category', 'nom')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->createOptionForm(CategoryResource::formSchema())
                    ->createOptionModalHeading(__('app.categorie.creer')),
            ])
            ->columns(2)
            ->mutateFormDataUsing(fn (array $data): array => $data + [
                'reference' => 'ART-'.str_pad((string) (Article::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'unite' => 'Unite',
                'actif' => true,
            ])
            ->successNotificationTitle(__('app.ajout_rapide.ajoute'));
    }

    /** A code was scanned: say if it is known, otherwise try to name it. */
    private function scanne(string $raw, Forms\Set $set, Forms\Get $get): void
    {
        $code = Barcode::normalizeScan($raw);

        if ($code === '') {
            return;
        }

        if ($code !== $raw) {
            $set('code_barre', $code);
        }

        if ($article = Article::where('code_barre', $code)->first()) {
            Notification::make()
                ->warning()
                ->title(__('app.ajout_rapide.existe', ['article' => $article->designation]))
                ->actions([
                    \Filament\Notifications\Actions\Action::make('modifier')
                        ->label(__('app.ajout_rapide.ouvrir'))
                        ->url(ArticleResource::getUrl('edit', ['record' => $article])),
                ])
                ->send();

            return;
        }

        if (filled($get('designation'))) {
            return;
        }

        if ($found = ProductLookup::find($code)) {
            $set('designation', $found['designation']);
            if ($found['marque'] && blank($get('marque'))) {
                $set('marque', $found['marque']);
            }
            Notification::make()->success()->title(__('app.ajout_rapide.trouve'))->send();
        } else {
            Notification::make()->info()->title(__('app.ajout_rapide.inconnu'))->send();
        }
    }
}
