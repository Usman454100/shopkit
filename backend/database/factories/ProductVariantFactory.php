<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_type' => 'size',
            'variant_value' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'stock_qty' => fake()->numberBetween(0, 50),
        ];
    }

    public function configure(): static
    {
        // store_id must match the parent product's store — keeps the factory
        // usable standalone (Product::factory() creates its own store) without
        // ever producing a variant that points at a different tenant than its product.
        return $this->afterMaking(function (ProductVariant $variant) {
            if (! $variant->store_id) {
                $variant->store_id = CurrentStore::bypass(
                    fn () => Product::find($variant->product_id)?->store_id
                );
            }
        });
    }
}
