<?php

namespace Database\Factories;

use App\Enums\ProductOptionType;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductOption>
 */
class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::title(fake()->unique()->word());

        return [
            'product_id' => Product::factory(),
            'type' => ProductOptionType::Color,
            'label' => $label,
            'value' => Str::slug($label),
            'price_delta' => 0,
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    public function color(): static
    {
        return $this->state(fn () => ['type' => ProductOptionType::Color]);
    }

    public function fabric(): static
    {
        return $this->state(fn () => [
            'type' => ProductOptionType::Fabric,
            'price_delta' => 5_000,
        ]);
    }

    public function size(string $value = 'M'): static
    {
        return $this->state(fn () => [
            'type' => ProductOptionType::Size,
            'label' => $value,
            'value' => $value,
            'price_delta' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
