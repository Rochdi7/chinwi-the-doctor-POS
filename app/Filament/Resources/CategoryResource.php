<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 21;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.donnees');
    }

    public static function getModelLabel(): string
    {
        return __('app.categorie.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.categorie.plural');
    }

    /**
     * Un seul champ : la categorie n'est qu'une etiquette de rangement,
     * elle doit se creer en une frappe depuis la fiche article.
     */
    public static function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nom')
                ->label(__('app.categorie.nom'))
                ->required()
                ->autofocus()
                ->maxLength(60)
                ->unique(table: 'categories', column: 'nom', ignoreRecord: true),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nom')
            ->columns([
                Tables\Columns\TextColumn::make('nom')
                    ->label(__('app.categorie.nom'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg'),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label(__('app.categorie.articles_count'))
                    ->counts('articles')
                    ->badge()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
        ];
    }
}
