<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => 'discount_only',
            'discount_type' => 'percentage',
            'discount_value' => fake()->randomFloat(2, 5, 30),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function withGift(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'discount_with_gift',
        ]);
    }

    public function giftOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'gift_only',
            'discount_type' => null,
            'discount_value' => null,
        ]);
    }
}
