<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration de l'administration.
 *
 * Hors production : mot de passe `password` par défaut (démonstration).
 * En production : les comptes ne sont créés que si SEED_ADMIN_PASSWORD est
 * défini (mot de passe fort) — sinon le seeder ne fait rien.
 * Les comptes réels peuvent aussi être créés via `php artisan stive:create-admin`.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_ADMIN_PASSWORD');

        if ($password === null || $password === '') {
            if (app()->isProduction()) {
                $this->command?->warn('SEED_ADMIN_PASSWORD non défini : aucun compte créé en production.');

                return;
            }

            $password = 'password';
        }

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
                    'password' => $password,
                    'email_verified_at' => now(),
                    'active_at' => now(),
                ],
            );
        }
    }
}
