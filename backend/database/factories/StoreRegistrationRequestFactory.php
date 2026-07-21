<?php

namespace Database\Factories;

use App\Models\StoreRegistrationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreRegistrationRequest>
 */
class StoreRegistrationRequestFactory extends Factory
{
    protected $model = StoreRegistrationRequest::class;

    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'category' => fake()->randomElement(['grocery', 'vegetable', 'shoe', 'other']),
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved', 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => fake()->sentence(),
            'reviewed_at' => now(),
        ]);
    }
}
