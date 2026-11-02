<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(30_000, 180_000);
        $quantity = fake()->numberBetween(1, 2);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_snapshot' => [
                'name' => 'Costume signature',
                'slug' => 'costume-signature',
                'base_price' => $unitPrice,
                'color' => 'Noir',
                'fabric' => 'Laine',
                'size' => 'M',
            ],
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $unitPrice * $quantity,
            'custom_size' => false,
        ];
    }

    public function customSize(): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_size' => true,
            'product_snapshot' => array_merge($attributes['product_snapshot'] ?? [], [
                'size' => 'Sur mesure',
            ]),
        ]);
    }
}
