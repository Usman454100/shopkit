<?php

namespace Database\Factories;

use App\Models\CustomerStoreJoin;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerStoreJoin>
 */
class CustomerStoreJoinFactory extends Factory
{
    protected $model = CustomerStoreJoin::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'store_id' => Store::factory(),
            'joined_at' => now(),
        ];
    }
}
