<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 5);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ];
    }

    public function configure(): static
    {
        // store_id must match the parent order's store — see ProductVariantFactory
        // for why this needs an explicit bypass of the fail-closed scope.
        return $this->afterMaking(function (OrderItem $item) {
            if (! $item->store_id) {
                $item->store_id = CurrentStore::bypass(
                    fn () => Order::find($item->order_id)?->store_id
                );
            }
        });
    }
}
