<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Support\Barcode;
use App\Support\Money;
use App\Support\Units;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.donnees');
    }

    public static function getModelLabel(): string
    {
        return __('app.article.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.article.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('designation')
                    ->label(__('app.article.designation'))
                    ->required()
                    ->autofocus()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('reference')
                    ->label(__('app.article.reference'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn () => 'ART-'.str_pad((string) (Article::max('id') + 1), 4, '0', STR_PAD_LEFT)),
                // Generated on create so every article is labelled, but still
                // editable: a product that already carries a manufacturer
                // barcode should keep its own number.
                Forms\Components\TextInput::make('code_barre')
                    ->label(__('app.article.code_barre'))
                    ->unique(ignoreRecord: true)
                    ->default(fn () => Barcode::generate())
                    ->helperText(__('app.article.code_barre_aide'))
                    ->live(onBlur: true)
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('regenerer')
                            ->label(__('app.article.code_barre_generer'))
                            ->icon('heroicon-o-arrow-path')
                            ->action(fn (Forms\Set $set) => $set('code_barre', Barcode::generate())),
                    ),

                // The label as it will print, plus a PNG download.
                Forms\Components\Placeholder::make('code_barre_apercu')
                    ->label(__('app.article.code_barre_apercu'))
                    ->content(function (Forms\Get $get, ?Article $record): HtmlString {
                        $code = trim((string) $get('code_barre'));

                        if ($code === '') {
                            return new HtmlString('<span class="text-gray-500">—</span>');
                        }

                        $img = '<img src="'.Barcode::dataUri($code).'" alt="'.e($code).'" style="height:60px">';

                        $link = $record?->exists && $record->code_barre === $code
                            ? '<a href="'.route('article.barcode', $record).'" target="_blank"
                                 class="text-primary-600 underline text-sm">'
                                .e(__('app.article.code_barre_telecharger')).'</a>'
                            : '';

                        return new HtmlString('<div class="space-y-2">'.$img.'<div>'.$link.'</div></div>');
                    }),
                // Fixed list rather than free text so the same unit is not
                // spelled three ways across the catalogue. A unit already
                // stored on the record is kept in the list so articles saved
                // before this list existed do not lose their value on edit.
                Forms\Components\Select::make('unite')
                    ->label(__('app.article.unite'))
                    ->options(fn (?Article $record) => self::uniteOptions($record))
                    // A Select does not check the submitted value on its own,
                    // so the rule is bound to the same list the menu is built
                    // from and the two cannot drift apart.
                    ->in(fn (?Article $record) => array_keys(self::uniteOptions($record)))
                    ->default('Unite')
                    ->required()
                    ->searchable()
                    ->native(false),
                Forms\Components\TextInput::make('prix_vente')
                    ->label(__('app.article.prix_vente'))
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->suffix('DH'),
                Forms\Components\TextInput::make('prix_achat')
                    ->label(__('app.article.prix_achat'))
                    ->numeric()
                    ->default(0)
                    ->suffix('DH'),
                Forms\Components\TextInput::make('stock')
                    ->label(__('app.article.stock'))
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('tva')
                    ->label(__('app.article.tva'))
                    ->numeric()
                    ->default(20)
                    ->suffix('%'),
                Forms\Components\Toggle::make('actif')
                    ->label(__('app.article.actif'))
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make(__('app.article.section_infos'))
                ->description(__('app.article.section_infos_aide'))
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('marque')->label(__('app.article.marque')),
                    // Choix rapide au clavier, et creation sur place : le commercant
                    // ne doit pas quitter la fiche article pour ranger un produit.
                    Forms\Components\Select::make('category_id')
                        ->label(__('app.article.categorie'))
                        ->relationship('category', 'nom')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->createOptionForm(CategoryResource::formSchema())
                        ->createOptionModalHeading(__('app.categorie.creer'))
                        ->editOptionForm(CategoryResource::formSchema())
                        ->editOptionModalHeading(__('app.categorie.modifier')),
                ])->columns(2),
        ]);
    }

    /**
     * The unit menu: the standard list, plus whatever the record already
     * holds so an article saved before the list existed keeps its unit.
     *
     * @return array<string, string>
     */
    protected static function uniteOptions(?Article $record): array
    {
        $current = $record?->unite;

        return Units::options() + ($current ? [$current => $current] : []);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('designation')
            ->columns([
                Tables\Columns\TextColumn::make('designation')
                    ->label(__('app.article.designation'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg')
                    ->description(fn (Article $r) => $r->reference),
                Tables\Columns\TextColumn::make('prix_vente')
                    ->label(__('app.article.prix_vente'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->size('lg'),
                Tables\Columns\TextColumn::make('stock')
                    ->label(__('app.article.stock'))
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->badge()
                    ->icon(fn ($state) => $state > 0 ? null : 'heroicon-o-exclamation-triangle')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('category.nom')
                    ->label(__('app.article.categorie'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('actif')
                    ->label(__('app.article.actif'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('app.article.categorie'))
                    ->relationship('category', 'nom')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('code_barre')
                    ->label(__('app.article.code_barre_telecharger'))
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->visible(fn (Article $record) => (bool) $record->code_barre)
                    ->url(fn (Article $record) => route('article.barcode', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
