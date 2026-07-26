<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_calculate_without_province_id_reports_shipping_as_not_yet_resolved(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $this->assertSame(100.0, (float) $response->json('data.total_before_shipping'));
        $this->assertNull($response->json('data.shipping_fee'));
        $this->assertTrue($response->json('data.shipping_required'));
        $this->assertFalse($response->json('data.is_final_total'));
        $this->assertSame(100.0, (float) $response->json('data.grand_total'));
    }

    public function test_cart_calculate_with_active_province_id_adds_shipping_fee_to_grand_total_only(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $province = Province::factory()->create(['shipping_fee' => 15000, 'is_active' => true]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'province_id' => $province->id,
        ]);

        $response->assertOk();
        $this->assertSame(100.0, (float) $response->json('data.total_before_shipping'));
        $this->assertSame(15000.0, (float) $response->json('data.shipping_fee'));
        $this->assertFalse($response->json('data.shipping_required'));
        $this->assertTrue($response->json('data.is_final_total'));
        $this->assertSame(15100.0, (float) $response->json('data.grand_total'));
    }

    public function test_cart_calculate_with_inactive_province_id_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $province = Province::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'province_id' => $province->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(false, $response->json('success'));
        $this->assertArrayHasKey('province_id', $response->json('errors'));
    }

    public function test_cart_calculate_with_nonexistent_province_id_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'province_id' => 999999,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('province_id', $response->json('errors'));
    }

    public function test_order_creation_without_province_id_returns_422(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('province_id', $response->json('errors'));
    }

    public function test_order_creation_with_inactive_province_id_returns_422(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
        $province = Province::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('province_id', $response->json('errors'));
    }

    public function test_order_shipping_fee_is_snapshotted_and_unaffected_by_later_province_price_change(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
        $province = Province::factory()->create(['shipping_fee' => 15000]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertCreated();
        $this->assertSame(15000.0, (float) $response->json('data.shipping_fee'));
        $this->assertSame(15100.0, (float) $response->json('data.total'));

        $orderId = $response->json('data.order_id');

        $province->update(['shipping_fee' => 99000]);

        $order = \App\Models\Order::findOrFail($orderId);
        $this->assertSame(15000.0, (float) $order->shipping_fee);
        $this->assertSame(15100.0, (float) $order->total);
    }
}
