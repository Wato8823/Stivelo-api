<?php

namespace Database\Factories;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Order::newReference(),
            'customer_name' => fake()->name(),
            'customer_phone' => '+2376'.fake()->numerify('########'),
            'customer_email' => null,
            'customer_city' => fake()->city(),
            'customer_note' => null,
            'status' => OrderStatus::PendingConfirmation,
            'total_estimate' => fake()->numberBetween(30_000, 250_000),
            'currency' => config('catalog.currency.code', 'XAF'),
            'channel' => OrderChannel::Whatsapp,
            'consent_given' => true,
            'source_ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
