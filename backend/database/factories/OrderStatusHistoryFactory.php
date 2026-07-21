<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'changed_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderStatusHistory $history) {
            if (! $history->store_id) {
                $history->store_id = CurrentStore::bypass(
                    fn () => Order::find($history->order_id)?->store_id
                );
            }
        });
    }
}
