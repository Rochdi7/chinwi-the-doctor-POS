<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use App\Support\AuditDiff;
use App\Support\Money;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.securite');
    }

    public static function getModelLabel(): string
    {
        return __('app.log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.log.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /**
     * Traduit un code d'événement, en retombant sur le code brut si aucune
     * traduction n'existe (ou si la clé pointe sur un groupe et non un texte).
     */
    public static function eventLabel(?string $event): string
    {
        if (blank($event)) {
            return '-';
        }

        $label = __('app.event.'.$event);

        return is_string($label) && $label !== 'app.event.'.$event ? $label : $event;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label(__('app.log.heure'))
                    ->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s.v'))
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user_name')
                    ->label(__('app.log.user'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('event')
                    ->label(__('app.log.event'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::eventLabel($state))
                    ->color(fn ($state) => match (true) {
                        str_starts_with($state, 'caisse') => 'warning',
                        $state === 'deleted' => 'danger',
                        $state === 'created' => 'success',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label(__('app.log.subject'))
                    ->formatStateUsing(fn ($state, ActivityLog $r) => $state ? $state.' #'.$r->subject_id : '-'),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('app.log.description'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('montant')
                    ->label(__('app.log.montant'))
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                Tables\Columns\TextColumn::make('id')
                    ->label(__('app.log.changement'))
                    ->wrap()
                    ->html()
                    ->formatStateUsing(fn (ActivityLog $r) => nl2br(e(AuditDiff::summary($r))))
                    ->tooltip(fn (ActivityLog $r) => AuditDiff::summary($r, 20)),
                Tables\Columns\TextColumn::make('remise_badge')
                    ->label(__('app.log.remise'))
                    ->badge()
                    ->color('danger')
                    ->state(fn (ActivityLog $r) => AuditDiff::remise($r))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ip')
                    ->label(__('app.log.ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label(__('app.log.event'))
                    ->options(fn () => ActivityLog::query()
                        ->distinct()
                        ->pluck('event')
                        ->mapWithKeys(fn (string $e) => [
                            $e => self::eventLabel($e),
                        ])
                        ->all()),
                Tables\Filters\Filter::make('occurred_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Du'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Au'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('app.log.voir'))
                    ->modalHeading(__('app.log.comparaison'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('app.log.fermer')),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('app.log.contexte'))
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('occurred_at')
                        ->label(__('app.log.heure'))
                        ->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s.v')),
                    Infolists\Components\TextEntry::make('user_name')
                        ->label(__('app.log.user'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('event')
                        ->label(__('app.log.event'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::eventLabel($state)),
                    Infolists\Components\TextEntry::make('subject_type')
                        ->label(__('app.log.subject'))
                        ->formatStateUsing(fn ($state, ActivityLog $r) => $state ? $state.' #'.$r->subject_id : '—'),
                    Infolists\Components\TextEntry::make('description')
                        ->label(__('app.log.description'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('montant')
                        ->label(__('app.log.montant'))
                        ->formatStateUsing(fn ($state) => $state === null ? '—' : Money::format($state)),
                    Infolists\Components\TextEntry::make('ip')
                        ->label(__('app.log.ip'))
                        ->placeholder('—'),
                ]),
            Infolists\Components\Section::make(__('app.log.comparaison'))
                ->schema([
                    Infolists\Components\ViewEntry::make('properties')
                        ->hiddenLabel()
                        ->view('filament.audit-diff'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
