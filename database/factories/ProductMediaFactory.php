<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    protected $model = ProductMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'disk' => 'demo',
            'storage_key' => 'products/'.Str::random(12).'.png',
            'alt' => 'Photographie du modèle',
            'sort_order' => fake()->numberBetween(1, 5),
            'width' => 800,
            'height' => 1000,
        ];
    }
}
