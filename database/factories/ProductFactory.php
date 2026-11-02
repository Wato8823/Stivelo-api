<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Modèle '.fake()->unique()->words(2, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'short_description' => fake()->sentence(10),
            'description' => fake()->paragraph(4),
            'collection_badge' => null,
            'base_price' => fake()->numberBetween(25_000, 180_000),
            'status' => ProductStatus::Published,
            'is_featured' => fake()->boolean(20),
            'custom_size_enabled' => true,
            'sort_order' => fake()->numberBetween(1, 30),
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Archived]);
    }
}
