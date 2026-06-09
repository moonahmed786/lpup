<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'quantity' => fake()->numberBetween(0, 1000),
            'price' => fake()->randomFloat(2, 5, 500),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(ProductStatus::cases()),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Active]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }
}
