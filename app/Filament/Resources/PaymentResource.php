<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 11;

    /**
     * Payments are taken at the till now, so this no longer needs its own
     * menu entry. The resource stays registered: receipts, edit links and
     * the invoice detail page all still route through it.
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.vente');
    }

    public static function getModelLabel(): string
    {
        return __('app.payment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.payment.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('client_id')
                    ->label(__('app.payment.client'))
                    // No preload(): the client list is searched on the server
                    // so the form stays fast as the file grows.
                    ->relationship(
                        'client',
                        'raison_sociale',
                        fn ($query, ?string $search) => $query->limit(50),
                    )
                    ->searchable()
                    ->placeholder(__('app.vente.client_passage'))
                    ->live(),
                Forms\Components\Select::make('invoice_id')
                    ->label(__('app.payment.invoice'))
                    ->options(fn (Get $get) => Invoice::query()
                        ->when($get('client_id'), fn ($q, $id) => $q->where('client_id', $id))
                        ->where('statut', '!=', 'payee')
                        ->orderByDesc('id')
                        ->limit(50)
                        ->pluck('numero', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $invoice = Invoice::query()
                            ->select('total_ttc', 'montant_paye')
                            ->whereKey($state)
                            ->first();

                        if ($invoice) {
                            $set('montant', (float) $invoice->total_ttc - (float) $invoice->montant_paye);
                        }
                    }),
                Forms\Components\TextInput::make('montant')
                    ->label(__('app.payment.montant'))
                    ->numeric()
                    ->required()
                    ->suffix('DH')
                    ->autofocus(),
                Forms\Components\DatePicker::make('date_paiement')
                    ->label(__('app.payment.date_paiement'))
                    ->default(now())
                    ->required(),
                Forms\Components\Radio::make('mode')
                    ->label(__('app.payment.mode'))
                    ->options([
                        'especes' => __('app.mode.especes'),
                        'tpe' => __('app.mode.tpe'),
                    ])
                    ->default('especes')
                    ->inline()
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('reference')
                    ->label(__('app.payment.reference'))
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_paiement', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date_paiement')
                    ->label(__('app.payment.date_paiement'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.raison_sociale')
                    ->label(__('app.payment.client'))
                    ->searchable()
                    ->size('lg')
                    ->placeholder(__('app.vente.client_passage')),
                Tables\Columns\TextColumn::make('invoice.numero')
                    ->label(__('app.payment.invoice'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('montant')
                    ->label(__('app.payment.montant'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->weight('bold')
                    ->size('lg')
                    ->color('success'),
                Tables\Columns\TextColumn::make('mode')
                    ->label(__('app.payment.mode'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('app.mode.'.$state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mode')
                    ->label(__('app.payment.mode'))
                    ->options([
                        'especes' => __('app.mode.especes'),
                        'tpe' => __('app.mode.tpe'),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')
                    ->label(__('app.receipt.print'))
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (Payment $record) => route('payment.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
