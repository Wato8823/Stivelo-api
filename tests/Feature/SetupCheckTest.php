<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le diagnostic doit passer sur un environnement correctement configuré
     * (c'est aussi une vérification de la commande elle-même en CI).
     */
    public function test_le_diagnostic_d_environnement_reussit(): void
    {
        $this->artisan('stive:check-setup')
            ->expectsOutputToContain('Diagnostic de l’environnement')
            ->assertExitCode(0);
    }

    public function test_la_base_de_test_est_bien_celle_utilisee_par_phpunit(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        $this->assertStringEndsWith('_test', $database);
    }
}
