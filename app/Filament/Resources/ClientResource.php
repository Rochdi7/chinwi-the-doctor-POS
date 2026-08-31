<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 21;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.donnees');
    }

    public static function getModelLabel(): string
    {
        return __('app.client.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.client.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('raison_sociale')
                    ->label(__('app.client.raison_sociale'))
                    ->required()
                    ->autofocus()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('telephone')
                    ->label(__('app.client.telephone'))
                    ->tel(),
                Forms\Components\TextInput::make('email')
                    ->label(__('app.client.email'))
                    ->email(),
                Forms\Components\Textarea::make('adresse')
                    ->label(__('app.client.adresse'))
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('actif')
                    ->label(__('app.client.actif'))
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('ICE / RC')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('ice')->label(__('app.client.ice')),
                    Forms\Components\TextInput::make('rc')->label(__('app.client.rc')),
                    Forms\Components\TextInput::make('numero_compte')->label(__('app.client.numero_compte')),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('raison_sociale')
            ->columns([
                Tables\Columns\TextColumn::make('raison_sociale')
                    ->label(__('app.client.raison_sociale'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg'),
                Tables\Columns\TextColumn::make('telephone')
                    ->label(__('app.client.telephone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('solde')
                    ->label(__('app.client.solde'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->size('lg'),
                Tables\Columns\IconColumn::make('actif')
                    ->label(__('app.client.actif'))
                    ->boolean(),
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
