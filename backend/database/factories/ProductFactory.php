<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
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
            'store_id' => Store::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'category' => fake()->word(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'pricing_type' => 'fixed',
            'base_price' => fake()->randomFloat(2, 10, 500),
            'unit' => 'pcs',
            'has_variants' => false,
            'is_perishable' => false,
            'is_active' => true,
        ];
    }

    public function weightBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_type' => 'weight_based',
            'unit' => 'kg',
        ]);
    }

    public function perishable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_perishable' => true,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'batch_number' => strtoupper(fake()->bothify('BATCH-####')),
        ]);
    }

    public function withVariants(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_variants' => true,
        ]);
    }
}
