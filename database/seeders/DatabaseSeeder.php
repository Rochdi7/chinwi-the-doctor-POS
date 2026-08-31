<?php

namespace Database\Seeders;

use App\Models\Caisse;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@local.test'],
            ['name' => 'Admin', 'password' => Hash::make('admin1234')],
        );

        Caisse::instance();

        $defaults = [
            'societe_nom' => 'chinwi_the_doctor',
            'societe_adresse' => '',
            'societe_telephone' => '',
            'societe_email' => '',
            'societe_ice' => '',
            'societe_rc' => '',
            'devise' => 'DH',
            'tva_defaut' => '20',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
