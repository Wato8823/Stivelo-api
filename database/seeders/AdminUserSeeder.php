<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration de l'administration.
 * En production, créer les comptes réels via `php artisan stive:create-admin`.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Direction Stive', 'email' => env('SEED_ADMIN_EMAIL', 'admin@stive-clothing.test'), 'role' => UserRole::Admin],
            ['name' => 'Conseiller boutique', 'email' => env('SEED_ADVISOR_EMAIL', 'conseiller@stive-clothing.test'), 'role' => UserRole::Advisor],
            ['name' => 'Atelier', 'email' => env('SEED_ATELIER_EMAIL', 'atelier@stive-clothing.test'), 'role' => UserRole::Atelier],
        ];

        foreach ($accounts as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'password' => env('SEED_ADMIN_PASSWORD', 'password'),
                    'email_verified_at' => now(),
                    'active_at' => now(),
                ],
            );
        }
    }
}
