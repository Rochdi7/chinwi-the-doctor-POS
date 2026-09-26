<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Parametres extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 41;

    protected static string $view = 'filament.pages.parametres';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.securite');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.setting.plural');
    }

    public function getTitle(): string
    {
        return __('app.setting.plural');
    }

    public function mount(): void
    {
        $this->form->fill([
            'societe_nom' => Setting::get('societe_nom'),
            'societe_adresse' => Setting::get('societe_adresse'),
            'societe_telephone' => Setting::get('societe_telephone'),
            'societe_email' => Setting::get('societe_email'),
            'societe_ice' => Setting::get('societe_ice'),
            'societe_rc' => Setting::get('societe_rc'),
            'devise' => Setting::get('devise', 'DH'),
            'tva_defaut' => Setting::get('tva_defaut', '20'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\TextInput::make('societe_nom')->label('Société / الشركة')->required(),
                    Forms\Components\TextInput::make('societe_telephone')->label('Téléphone / الهاتف'),
                    Forms\Components\Textarea::make('societe_adresse')->label('Adresse / العنوان')->rows(2)->columnSpanFull(),
                    Forms\Components\TextInput::make('societe_email')->label('Email')->email(),
                    Forms\Components\TextInput::make('societe_ice')->label('ICE'),
                    Forms\Components\TextInput::make('societe_rc')->label('RC'),
                    Forms\Components\TextInput::make('devise')->label('Devise / العملة')->default('DH'),
                    Forms\Components\TextInput::make('tva_defaut')->label('TVA % / الضريبة')->numeric()->default(20),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        ActivityLog::record('settings.updated', null, 'Paramètres modifiés');

        Notification::make()->title('OK')->success()->send();
    }
}
