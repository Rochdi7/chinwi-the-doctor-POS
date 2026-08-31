<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Article;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.vente');
    }

    public static function getModelLabel(): string
    {
        return __('app.invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.invoice.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('client_id')
                    ->label(__('app.invoice.client'))
                    ->relationship(
                        'client',
                        'raison_sociale',
                        fn ($query) => $query->limit(50),
                    )
                    ->searchable()
                    // Optional: a counter sale is invoiced to nobody in
                    // particular. Left empty it prints as a walk-in.
                    ->placeholder(__('app.vente.client_passage'))
                    ->createOptionForm([
                        Forms\Components\TextInput::make('raison_sociale')
                            ->label(__('app.client.raison_sociale'))
                            ->required(),
                        Forms\Components\TextInput::make('telephone')
                            ->label(__('app.client.telephone')),
                    ]),
                Forms\Components\DatePicker::make('date_facture')
                    ->label(__('app.invoice.date_facture'))
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('numero')
                    ->label(__('app.invoice.numero'))
                    ->default(fn () => Invoice::nextNumero())
                    ->required()
                    ->unique(ignoreRecord: true),

                // Read-only: the status follows the payments, never the user.
                Forms\Components\Placeholder::make('statut_affiche')
                    ->label(__('app.invoice.statut'))
                    ->content(fn (?Invoice $record) => $record
                        ? __('app.statut.'.$record->statut).' — '.__('app.invoice.reste').' : '.Money::format($record->reste())
                        : __('app.statut.validee')),
            ])->columns(2),

            Forms\Components\Section::make(__('app.invoice.items'))
                ->description(__('app.invoice.items_aide'))
                ->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->label('')
                    // Spelled out rather than a bare "+": the button has to
                    // say what it adds for someone who does not read easily.
                    ->addActionLabel(__('app.invoice.ajouter_article'))
                    ->defaultItems(1)
                    ->schema([
                        Forms\Components\Select::make('article_id')
                            ->label(__('app.item.article'))
                            // Search server-side instead of loading the whole
                            // catalogue into every repeater row on each render.
                            ->searchable()
                            // Without options() the list is empty until the
                            // user types, which reads as a stuck "En
                            // recherche...". Show the first articles straight
                            // away, then narrow them by search.
                            ->options(fn () => Article::query()
                                ->where('actif', true)
                                ->orderBy('designation')
                                ->limit(50)
                                ->pluck('designation', 'id'))
                            ->getSearchResultsUsing(fn (string $search) => Article::query()
                                ->where('actif', true)
                                ->where(fn ($q) => $q
                                    ->where('designation', 'like', "%{$search}%")
                                    ->orWhere('reference', 'like', "%{$search}%")
                                    ->orWhere('code_barre', 'like', "%{$search}%"))
                                ->limit(50)
                                ->pluck('designation', 'id'))
                            ->getOptionLabelUsing(fn ($value) => Article::whereKey($value)->value('designation'))
                            ->noSearchResultsMessage(__('app.invoice.aucun_article_trouve'))
                            ->loadingMessage(__('app.invoice.chargement'))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $article = Article::query()
                                    ->select('designation', 'prix_vente', 'tva')
                                    ->whereKey($state)
                                    ->first();

                                if ($article) {
                                    $set('designation', $article->designation);
                                    $set('prix_unitaire', $article->prix_vente);
                                    $set('tva', $article->tva);
                                }
                            })
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('designation')
                            ->label(__('app.item.designation'))
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('quantite')
                            ->label(__('app.item.quantite'))
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live(debounce: 400),
                        Forms\Components\TextInput::make('prix_unitaire')
                            ->label(__('app.item.prix_unitaire'))
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->live(debounce: 400),
                        Forms\Components\TextInput::make('remise')
                            ->label(__('app.item.remise'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            // A discount larger than the line would silently be
                            // clamped to 0 by computeTotals(); refuse it instead.
                            ->maxValue(fn (Get $get) => (float) $get('quantite') * (float) $get('prix_unitaire'))
                            ->helperText(fn (Get $get): string => Money::format(
                                (float) $get('quantite') * (float) $get('prix_unitaire')
                            ))
                            ->live(debounce: 400),
                        Forms\Components\TextInput::make('tva')
                            ->label(__('app.item.tva'))
                            ->numeric()
                            ->default(20)
                            ->live(debounce: 400),
                        Forms\Components\Placeholder::make('ligne_total')
                            ->label(__('app.item.total_ttc'))
                            ->content(function (Get $get): string {
                                $brut = (float) $get('quantite') * (float) $get('prix_unitaire');
                                $remise = (float) $get('remise');
                                $ht = max($brut - $remise, 0);
                                $ttc = Money::format($ht * (1 + (float) $get('tva') / 100));

                                // Spell the discount out: the TTC alone cannot
                                // show whether the remise was actually applied.
                                return $remise > 0
                                    ? $ttc.'  ('.Money::format($brut).' − '.Money::format($remise).')'
                                    : $ttc;
                            }),
                    ])
                    ->columns(4)
                    ->live(),

                Forms\Components\Placeholder::make('facture_total')
                    ->label(__('app.item.grand_total'))
                    ->content(function (Get $get): string {
                        $ht = 0.0;
                        $ttc = 0.0;

                        foreach ((array) $get('items') as $line) {
                            $ligneHt = max(
                                ((float) ($line['quantite'] ?? 0) * (float) ($line['prix_unitaire'] ?? 0))
                                - (float) ($line['remise'] ?? 0),
                                0
                            );
                            $ht += $ligneHt;
                            $ttc += $ligneHt * (1 + (float) ($line['tva'] ?? 0) / 100);
                        }

                        return __('app.item.total_ht').' : '.Money::format($ht)
                            .'   |   '.__('app.item.tva').' : '.Money::format($ttc - $ht)
                            .'   |   '.__('app.item.grand_total').' : '.Money::format($ttc);
                    }),
            ]),

            // Pay at the counter, in the same screen that records the sale.
            // Only on create: an existing sale is settled with Encaisser, so
            // its payment history stays a list of real events.
            Forms\Components\Section::make(__('app.vente.section'))
                ->visibleOn('create')
                ->schema([
                    Forms\Components\Toggle::make('encaisser_maintenant')
                        ->label(__('app.vente.encaisse_maintenant'))
                        ->helperText(__('app.vente.rien_paye'))
                        ->default(true)
                        ->live()
                        ->dehydrated(false),

                    Forms\Components\Radio::make('mode_paiement')
                        ->label(__('app.payment.mode'))
                        ->options([
                            'especes' => __('app.mode.especes'),
                            'tpe' => __('app.mode.tpe'),
                        ])
                        ->default('especes')
                        ->inline()
                        ->visible(fn (Forms\Get $get) => (bool) $get('encaisser_maintenant'))
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('montant_recu')
                        ->label(__('app.vente.montant_recu'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('DH')
                        ->helperText(__('app.invoice.total_ttc'))
                        ->visible(fn (Forms\Get $get) => (bool) $get('encaisser_maintenant'))
                        ->dehydrated(false),
                ])->columns(3),

            Forms\Components\Textarea::make('note')
                ->label(__('app.invoice.note'))
                ->rows(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('numero')
                    ->label(__('app.invoice.numero'))
                    ->weight('bold'),
                Infolists\Components\TextEntry::make('date_facture')
                    ->label(__('app.invoice.date_facture'))
                    ->date('d/m/Y'),
                Infolists\Components\TextEntry::make('client.raison_sociale')
                    ->label(__('app.invoice.client'))
                    ->state(fn (Invoice $record) => $record->clientNom()),
                Infolists\Components\TextEntry::make('statut')
                    ->label(__('app.invoice.statut'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('app.statut.'.$state))
                    ->color(fn ($state) => Invoice::statutColor($state)),
            ])->columns(2),

            Infolists\Components\Section::make(__('app.invoice.items'))->schema([
                Infolists\Components\RepeatableEntry::make('items')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('designation')
                            ->label(__('app.item.designation')),
                        Infolists\Components\TextEntry::make('quantite')
                            ->label(__('app.item.quantite'))
                            ->numeric(),
                        Infolists\Components\TextEntry::make('prix_unitaire')
                            ->label(__('app.item.prix_unitaire'))
                            ->formatStateUsing(fn ($state) => Money::format($state)),
                        Infolists\Components\TextEntry::make('remise')
                            ->label(__('app.item.remise'))
                            ->formatStateUsing(fn ($state) => Money::format($state)),
                        Infolists\Components\TextEntry::make('total_ttc')
                            ->label(__('app.item.total_ttc'))
                            ->formatStateUsing(fn ($state) => Money::format($state))
                            ->weight('bold'),
                    ])
                    ->columns(5),
            ]),

            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('total_ht')
                    ->label(__('app.invoice.total_ht'))
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                Infolists\Components\TextEntry::make('total_tva')
                    ->label(__('app.invoice.total_tva'))
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                Infolists\Components\TextEntry::make('total_ttc')
                    ->label(__('app.invoice.total_ttc'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold')
                    ->size('lg'),
                Infolists\Components\TextEntry::make('montant_paye')
                    ->label(__('app.invoice.montant_paye'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->color('success'),
                Infolists\Components\TextEntry::make('reste')
                    ->label(__('app.invoice.reste'))
                    ->state(fn (Invoice $record) => Money::format(max($record->reste(), 0)))
                    ->weight('bold')
                    ->color('danger'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_facture', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label(__('app.invoice.numero'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('date_facture')
                    ->label(__('app.invoice.date_facture'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.raison_sociale')
                    ->label(__('app.invoice.client'))
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->placeholder(__('app.vente.client_passage')),
                Tables\Columns\TextColumn::make('total_ttc')
                    ->label(__('app.invoice.total_ttc'))
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->size('lg')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('montant_paye')
                    ->label(__('app.invoice.montant_paye'))
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                Tables\Columns\TextColumn::make('statut')
                    ->label(__('app.invoice.statut'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('app.statut.'.$state))
                    ->color(fn ($state) => Invoice::statutColor($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label(__('app.invoice.client'))
                    ->relationship('client', 'raison_sociale')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('statut')
                    ->label(__('app.invoice.statut'))
                    ->options(Invoice::statutOptions()),
            ])
            ->actions([
                // Règlements has no menu entry any more: an unpaid invoice is
                // settled straight from its row.
                Tables\Actions\Action::make('encaisser')
                    ->label(__('app.invoice.encaisser'))
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (Invoice $record) => $record->reste() > 0)
                    ->modalSubmitActionLabel(__('app.invoice.encaisser'))
                    ->form(fn (Invoice $record) => [
                        Forms\Components\TextInput::make('montant')
                            ->label(__('app.payment.montant'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($record->reste())
                            ->default($record->reste())
                            ->suffix('DH')
                            ->autofocus(),
                        Forms\Components\Radio::make('mode')
                            ->label(__('app.payment.mode'))
                            ->options([
                                'especes' => __('app.mode.especes'),
                                'tpe' => __('app.mode.tpe'),
                            ])
                            ->default('especes')
                            ->inline()
                            ->required(),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $payment = \App\Models\Payment::create([
                            'invoice_id' => $record->id,
                            'client_id' => $record->client_id,
                            'user_id' => auth()->id(),
                            'date_paiement' => now(),
                            'montant' => $data['montant'],
                            'mode' => $data['mode'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title(__('app.vente.paye'))
                            ->success()
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('print')
                                    ->label(__('app.receipt.print'))
                                    ->url(route('payment.pdf', $payment))
                                    ->openUrlInNewTab()
                                    ->button(),
                            ])
                            ->send();
                    }),
                Tables\Actions\Action::make('print')
                    ->label(__('app.invoice.print'))
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (Invoice $record) => route('invoice.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\ViewAction::make()->label(''),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
