<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jeu de données de développement : catalogue complet, comptes
     * d'administration et demandes de démonstration.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            CatalogSeeder::class,
            AdminUserSeeder::class,
        ]);

        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call(DemoOrderSeeder::class);
        }
    }
}
