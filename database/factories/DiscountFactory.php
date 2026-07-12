<?php

namespace Database\Factories;

use App\Models\Discount;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => 'percentage',
            'value' => fake()->randomFloat(2, 5, 50),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}
