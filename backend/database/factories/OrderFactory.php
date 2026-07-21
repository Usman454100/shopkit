<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'customer_id' => User::factory(),
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => fake()->address(),
            'total_amount' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
