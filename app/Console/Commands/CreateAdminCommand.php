<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Création d'un compte interne sans passer par les seeders :
 *
 *   php artisan stive:create-admin direction@stive-clothing.cm --role=admin
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'stive:create-admin
                            {email : Adresse e-mail du compte}
                            {--name= : Nom affiché}
                            {--role=admin : admin, advisor ou atelier}
                            {--password= : Mot de passe (demandé de façon masquée si omis)}';

    protected $description = 'Crée (ou réactive) un compte de l’administration Stive Clothing';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Rôle inconnu. Valeurs acceptées : admin, advisor, atelier.');

            return self::FAILURE;
        }

        $data = [
            'email' => (string) $this->argument('email'),
            'name' => (string) ($this->option('name') ?: 'Compte '.$role->label()),
            'password' => (string) ($this->option('password') ?: $this->secret('Mot de passe (10 caractères minimum)')),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:10', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'role' => $role,
                'password' => $data['password'],
                'email_verified_at' => now(),
                'active_at' => now(),
            ],
        );

        $this->info(sprintf('Compte %s prêt : %s (%s).', $user->wasRecentlyCreated ? 'créé' : 'mis à jour', $user->email, $role->value));

        return self::SUCCESS;
    }
}
