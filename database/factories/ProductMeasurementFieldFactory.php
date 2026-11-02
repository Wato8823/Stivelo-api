<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMeasurementField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMeasurementField>
 */
class ProductMeasurementFieldFactory extends Factory
{
    protected $model = ProductMeasurementField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'key' => 'chest_cm',
            'label' => 'Tour de poitrine',
            'hint' => 'Mesurez au niveau le plus fort, sans serrer.',
            'is_required' => true,
            'min_cm' => (int) config('catalog.measurements.min', 20),
            'max_cm' => (int) config('catalog.measurements.max', 250),
            'sort_order' => fake()->numberBetween(1, 8),
        ];
    }

    public function optional(): static
    {
        return $this->state(fn () => ['is_required' => false]);
    }
}
