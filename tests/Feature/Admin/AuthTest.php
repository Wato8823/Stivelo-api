<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_autorise_obtient_un_jeton_et_ses_droits(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'direction@stive-clothing.test',
            'password' => 'mot-de-passe-solide',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'direction@stive-clothing.test',
            'password' => 'mot-de-passe-solide',
            'device_name' => 'navigateur-direction',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_at',
                'user' => ['id', 'name', 'email', 'role', 'permissions'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.permissions.manage_catalogue', true);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'navigateur-direction',
        ]);
    }

    public function test_des_identifiants_incorrects_sont_refuses(): void
    {
        User::factory()->create([
            'email' => 'conseiller@stive-clothing.test',
            'password' => 'mot-de-passe-solide',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'conseiller@stive-clothing.test',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonStructure(['message', 'code', 'errors', 'request_id']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->inactive()->create([
            'email' => 'atelier@stive-clothing.test',
            'password' => 'mot-de-passe-solide',
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'atelier@stive-clothing.test',
            'password' => 'mot-de-passe-solide',
        ])->assertStatus(403)->assertJsonPath('code', 'account_disabled');
    }

    public function test_les_donnees_invalides_suivent_le_format_d_erreur_unique(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'pas-une-adresse',
            'password' => 'court',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email', 'password'], 'request_id']);
    }

    public function test_le_profil_est_protege_par_un_jeton(): void
    {
        $this->getJson('/api/v1/admin/me')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_la_deconnexion_revoque_le_jeton_courant(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_la_connexion_est_limitee_en_debit(): void
    {
        config()->set('catalog.rate_limits.login_per_minute', 2);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/admin/login', [
                'email' => 'inconnu@stive-clothing.test',
                'password' => 'mot-de-passe-solide',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/admin/login', [
            'email' => 'inconnu@stive-clothing.test',
            'password' => 'mot-de-passe-solide',
        ])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }
}
