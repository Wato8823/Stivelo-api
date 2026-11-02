<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Advisor,
            'active_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function atelier(): static
    {
        return $this->state(fn () => ['role' => UserRole::Atelier]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active_at' => now()->addDay()]);
    }
}
