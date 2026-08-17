<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('COUP-####')),
            'type' => fake()->randomElement(['percentage', 'fixed']),
            'value' => fake()->randomFloat(2, 5, 50),
            'max_discount_amount' => null,
            'scope' => 'general',
            'phone_number' => null,
            'min_order_amount' => null,
            'usage_limit' => null,
            'per_customer_usage_limit' => null,
            'used_count' => 0,
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function forUser(string $phoneNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'user',
            'phone_number' => $phoneNumber,
        ]);
    }
}
