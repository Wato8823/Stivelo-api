<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou exécuté avant toute migration lancée par RefreshDatabase.
     *
     * Les tests utilisent `migrate:fresh` : si la connexion pointait par
     * erreur vers la base de développement, toutes les données réelles
     * seraient effacées. On refuse donc net toute base dont le nom ne se
     * termine pas par « _test ».
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            return;
        }

        if (str_ends_with($database, '_test')) {
            return;
        }

        throw new RuntimeException(implode(' ', [
            "Les tests refusent de démarrer sur la base « {$database} »,",
            'car son nom ne se termine pas par « _test ».',
            'Vérifiez la valeur DB_DATABASE de phpunit.xml ou de .env.testing,',
            'puis créez la base de test : php artisan stive:check-setup vous indique la commande exacte.',
        ]));
    }
}
