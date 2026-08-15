<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $stock = fake()->numberBetween(5, 100);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 500),
            'price_usd' => fake()->randomFloat(2, 1, 50),
            'category_id' => Category::factory(),
            'is_active' => true,
            'extra_info' => null,
            'stock_quantity' => $stock,
            'reserved_quantity' => 0,
        ];
    }

    /**
     * منتج نافد الكمية بالكامل (available_quantity = 0) لاختبار سيناريو منع الطلب.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 10,
            'reserved_quantity' => 10,
        ]);
    }

    /**
     * منتج بدون أي مخزون فعلي بالمستودع.
     */
    public function noStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    /**
     * منتج بكمية محجوزة جزئياً (بعض المتاح وبعضه محجوز).
     */
    public function partiallyReserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 20,
            'reserved_quantity' => 15,
        ]);
    }
}
