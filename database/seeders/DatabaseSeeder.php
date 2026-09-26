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
        User::firstOrCreate(
            ['email' => 'admin@local.test'],
            ['name' => 'Admin', 'password' => Hash::make('admin1234')],
        );

        // Passwords live in .env only: this repo is public.
        $accounts = [
            ['email' => 'mehdi@gmail.com', 'name' => 'Mehdi', 'env' => 'SEED_MEHDI_PASSWORD'],
            ['email' => 'caissier@gmail.com', 'name' => 'Caissier', 'env' => 'SEED_CAISSIER_PASSWORD'],
        ];

        foreach ($accounts as $account) {
            $password = env($account['env']);

            if (! $password) {
                $this->command?->warn("{$account['email']} skipped: {$account['env']} is not set in .env");

                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => Hash::make($password)],
            );

            $this->command?->info($account['email'].($user->wasRecentlyCreated ? ' created' : ' already exists'));
        }

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
