<?php

namespace Database\Factories;

use App\Models\Banner;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(6),
            'product_id' => Product::factory(),
            'priority' => fake()->numberBetween(0, 10),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(10),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(3),
        ]);
    }
}
