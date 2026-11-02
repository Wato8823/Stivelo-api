<?php

namespace Database\Factories;

use App\Models\Measurement;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    protected $model = Measurement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'key' => 'chest_cm',
            'label' => 'Tour de poitrine',
            'value' => fake()->randomFloat(1, 80, 120),
            'unit' => 'cm',
        ];
    }
}
