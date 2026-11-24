<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jeu de données de développement : catalogue complet, comptes
     * d'administration et demandes de démonstration.
     *
     * En production (Render), on ne lance QUE le catalogue (idempotent) :
     * les comptes réels se créent avec `php artisan stive:create-admin`,
     * jamais avec les comptes de démonstration.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            CatalogSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([
                AdminUserSeeder::class,
                DemoOrderSeeder::class,
            ]);

            return;
        }

        // Production : comptes uniquement via SEED_ADMIN_* (mot de passe fort exigé),
        // sinon rien n'est créé — pas de mot de passe par défaut.
        if (env('SEED_ADMIN_EMAIL') && env('SEED_ADMIN_PASSWORD')) {
            $this->call(AdminUserSeeder::class);
        }
    }
}
