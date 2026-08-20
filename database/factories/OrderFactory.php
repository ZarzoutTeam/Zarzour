<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 300);
        $shippingFee = fake()->randomFloat(2, 1000, 10000);

        return [
            'customer_name' => fake()->name(),
            'phone_number' => fake()->numerify('09########'),
            'province_id' => Province::factory(),
            'shipping_address' => fake()->address(),
            'extra_notes' => null,
            'subtotal' => $subtotal,
            'subtotal_usd' => null,
            'discount_amount' => 0,
            'discount_amount_usd' => null,
            'coupon_discount_amount' => 0,
            'coupon_discount_amount_usd' => null,
            'shipping_fee' => $shippingFee,
            'shipping_fee_usd' => null,
            'total' => $subtotal + $shippingFee,
            'total_usd' => null,
            'coupon_id' => null,
            'applied_offer_id' => null,
            'payment_method' => fake()->randomElement(['cod', 'sham_cash', 'visa_ui']),
            'currency' => 'SYP',
            'exchange_rate_snapshot' => null,
            'status' => 'pending',
        ];
    }
}
