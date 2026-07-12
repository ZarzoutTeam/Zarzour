<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function orderPayload(Product $product, array $overrides = []): array
    {
        $province = Province::factory()->create();

        return array_merge([
            'customer_name' => 'أحمد علي',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'شارع الوحدة',
            'payment_method' => 'cod',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ], $overrides);
    }

    public function test_order_created_by_authenticated_customer_has_user_id_set(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        Sanctum::actingAs($user, ['*']);

        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);

        $response = $this->postJson('/api/v1/orders', $this->orderPayload($product));

        $response->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);
    }

    public function test_order_created_by_guest_has_null_user_id_and_succeeds(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);

        $response = $this->postJson('/api/v1/orders', $this->orderPayload($product));

        $response->assertCreated();

        $order = Order::firstOrFail();
        $this->assertNull($order->user_id);
    }

    public function test_customers_user_id_is_synced_when_authenticated_customer_places_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        Sanctum::actingAs($user, ['*']);

        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);

        $this->postJson('/api/v1/orders', $this->orderPayload($product, ['phone_number' => '0922222222']))
            ->assertCreated();

        $customer = Customer::where('phone_number', '0922222222')->firstOrFail();
        $this->assertSame($user->id, $customer->user_id);
    }

    public function test_my_orders_returns_only_authenticated_users_own_orders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $otherUser = User::factory()->create();
        $otherUser->assignRole('customer');

        Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $otherUser->id]);
        Order::factory()->create(['user_id' => null]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/me/orders');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_my_orders_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/me/orders')->assertUnauthorized();
    }

    public function test_customer_can_cancel_own_pending_order_and_stock_reservation_is_released(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 0]);
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_snapshot' => $product->price,
            'quantity' => 2,
            'line_total' => $product->price * 2,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/v1/me/orders/{$order->id}/cancel");

        $response->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);

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

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('customer');
        $intruder = User::factory()->create();
        $intruder->assignRole('customer');

        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);

        Sanctum::actingAs($intruder, ['*']);

        $response = $this->postJson("/api/v1/me/orders/{$order->id}/cancel");

        $response->assertNotFound();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_customer_cannot_cancel_confirmed_shipped_or_delivered_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        Sanctum::actingAs($user, ['*']);

        foreach (['confirmed', 'shipped', 'delivered'] as $status) {
            $order = Order::factory()->create(['user_id' => $user->id, 'status' => $status]);

            $response = $this->postJson("/api/v1/me/orders/{$order->id}/cancel");

            $response->assertStatus(422);
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_valid_admin_status_transitions_succeed(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $order->update(['status' => 'confirmed']);
        $this->assertSame('confirmed', $order->fresh()->status);

        $order->update(['status' => 'shipped']);
        $this->assertSame('shipped', $order->fresh()->status);

        $order->update(['status' => 'delivered']);
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_invalid_admin_status_transitions_are_rejected(): void
    {
        $pendingOrder = Order::factory()->create(['status' => 'pending']);
        $this->expectExceptionMessageMatches('/pending.*shipped/');
        $pendingOrder->update(['status' => 'shipped']);
    }

    public function test_shipped_to_cancelled_transition_is_rejected(): void
    {
        $order = Order::factory()->create(['status' => 'shipped']);

        try {
            $order->update(['status' => 'cancelled']);
            $this->fail('Expected InvalidOrderStatusTransitionException was not thrown.');
        } catch (InvalidOrderStatusTransitionException $e) {
            $this->assertSame('shipped', $order->fresh()->status);
        }
    }

    public function test_delivered_is_a_terminal_status(): void
    {
        $order = Order::factory()->create(['status' => 'delivered']);

        try {
            $order->update(['status' => 'cancelled']);
            $this->fail('Expected InvalidOrderStatusTransitionException was not thrown.');
        } catch (InvalidOrderStatusTransitionException $e) {
            $this->assertSame('delivered', $order->fresh()->status);
        }
    }

    public function test_confirming_order_still_deducts_stock_through_transition_layer(): void
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
}
