<?php

namespace Tests\Feature;

use App\Events\OutOfStockAttempted;
use App\Exceptions\OutOfStockException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_pending_order_reserves_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);
        $order = Order::factory()->create(['status' => 'pending']);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => 3,
            'line_total' => $product->price * 3,
        ]);

        $product->refresh();

        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(3, $product->reserved_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'reservation',
            'quantity' => 3,
        ]);
    }

    public function test_order_exceeding_available_quantity_is_blocked_and_fires_event(): void
    {
        Event::fake([OutOfStockAttempted::class]);

        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 3]);
        $order = Order::factory()->create(['status' => 'pending', 'phone_number' => '0911111111']);

        $this->expectException(OutOfStockException::class);

        try {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'unit_price_snapshot' => $product->price,
                'quantity' => 8,
                'line_total' => $product->price * 8,
            ]);
        } finally {
            Event::assertDispatched(
                OutOfStockAttempted::class,
                fn (OutOfStockAttempted $event) => $event->productId === $product->id
                    && $event->requestedQuantity === 8
                    && $event->phoneNumber === '0911111111'
            );

            $product->refresh();
            $this->assertSame(3, $product->reserved_quantity);
            $this->assertDatabaseCount('order_items', 0);
        }
    }

    public function test_confirming_order_deducts_stock_and_clears_reservation(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);
        $order = Order::factory()->create(['status' => 'pending']);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => 4,
            'line_total' => $product->price * 4,
        ]);

        $order->update(['status' => 'confirmed']);

        $product->refresh();

        $this->assertSame(6, $product->stock_quantity);
        $this->assertSame(0, $product->reserved_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'confirmed_deduction',
            'quantity' => 4,
        ]);
    }

    public function test_cancelling_pending_order_releases_reservation_without_touching_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);
        $order = Order::factory()->create(['status' => 'pending']);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => 2,
            'line_total' => $product->price * 2,
        ]);

        $order->update(['status' => 'cancelled']);

        $product->refresh();

        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(0, $product->reserved_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'release',
            'quantity' => 2,
        ]);
    }

    public function test_release_expired_stock_reservations_command_releases_and_expires_order(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);
        $order = Order::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subHour(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => 5,
            'line_total' => $product->price * 5,
        ]);

        $this->artisan('orders:release-expired-reservations')->assertSuccessful();

        $product->refresh();
        $order->refresh();

        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(0, $product->reserved_quantity);
        $this->assertSame('expired', $order->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'expired_release',
            'quantity' => 5,
        ]);
    }
}
