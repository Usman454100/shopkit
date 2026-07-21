<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity_on_hand' => fake()->randomFloat(2, 0, 200),
            'reorder_level' => 10,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Inventory $inventory) {
            if (! $inventory->store_id) {
                $inventory->store_id = CurrentStore::bypass(
                    fn () => Product::find($inventory->product_id)?->store_id
                );
            }
        });
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => 2,
            'reorder_level' => 10,
        ]);
    }
}
