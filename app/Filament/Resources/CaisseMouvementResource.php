<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CaisseMouvementResource\Pages;
use App\Models\CaisseMouvement;
use App\Support\Money;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CaisseMouvementResource extends Resource
{
    protected static ?string $model = CaisseMouvement::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.caisse');
    }

    public static function getModelLabel(): string
    {
        return __('app.caisse.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.caisse.mouvements');
    }

    /**
     * The caisse journal is evidence, not a worksheet: it is written only by
     * the payment flow and can never be created, edited or deleted by hand.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label(__('app.caisse.heure'))
                    ->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s.v'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('app.caisse.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('app.caisse.'.$state))
                    ->color(fn ($state) => $state === 'entree' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('montant')
                    ->label(__('app.caisse.montant'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold')
                    ->size('lg'),
                Tables\Columns\TextColumn::make('motif')
                    ->label(__('app.caisse.motif'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('app.log.user')),
                Tables\Columns\TextColumn::make('solde_apres')
                    ->label(__('app.caisse.solde_apres'))
                    ->formatStateUsing(fn ($state) => Money::format($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('app.caisse.type'))
                    ->options([
                        'entree' => __('app.caisse.entree'),
                        'sortie' => __('app.caisse.sortie'),
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaisseMouvements::route('/'),
        ];
    }
}
