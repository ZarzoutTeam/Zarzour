<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\HomepageSetting;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDualCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_invoice_snapshots_syp_usd_and_the_checkout_exchange_rate(): void
    {
        HomepageSetting::create([
            'usd_to_syp_rate' => 10000,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $product = Product::factory()->create([
            'price' => 1,
            'price_usd' => 10,
            'stock_quantity' => 10,
        ]);
        Discount::factory()->create([
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 1,
            'value_usd' => 1,
        ]);
        Coupon::factory()->create([
            'code' => 'INVOICE2',
            'type' => 'fixed',
            'value' => 1,
            'value_usd' => 2,
        ]);
        Offer::factory()->create([
            'product_id' => $product->id,
            'discount_type' => 'fixed',
            'discount_value' => 1,
            'discount_value_usd' => 0.5,
        ]);
        $province = Province::factory()->create([
            'shipping_fee' => 1,
            'shipping_fee_usd' => 1.5,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'coupon_code' => 'INVOICE2',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.currency', 'SYP')
            ->assertJsonPath('data.exchange_rate.rate', 10000)
            ->assertJsonPath('data.amounts.subtotal.SYP', 100000)
            ->assertJsonPath('data.amounts.subtotal.USD', 10)
            ->assertJsonPath('data.amounts.discount_amount.SYP', 35000)
            ->assertJsonPath('data.amounts.discount_amount.USD', 3.5)
            ->assertJsonPath('data.amounts.shipping_fee.SYP', 15000)
            ->assertJsonPath('data.amounts.shipping_fee.USD', 1.5)
            ->assertJsonPath('data.amounts.total.SYP', 80000)
            ->assertJsonPath('data.amounts.total.USD', 8)
            ->assertJsonPath('data.items.0.amounts.unit_price.USD', 10)
            ->assertJsonPath('data.items.0.amounts.direct_discount_amount.USD', 1)
            ->assertJsonPath('data.items.0.amounts.coupon_discount_amount.USD', 2)
            ->assertJsonPath('data.items.0.amounts.offer_discount_amount.USD', 0.5)
            ->assertJsonPath('data.items.0.amounts.line_total.USD', 6.5);

        $order = Order::findOrFail($response->json('data.order_id'));
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(10000.0, (float) $order->exchange_rate_snapshot);
        $this->assertSame(10.0, (float) $order->subtotal_usd);
        $this->assertSame(3.5, (float) $order->discount_amount_usd);
        $this->assertSame(2.0, (float) $order->coupon_discount_amount_usd);
        $this->assertSame(1.5, (float) $order->shipping_fee_usd);
        $this->assertSame(8.0, (float) $order->total_usd);
        $this->assertSame(10.0, (float) $item->unit_price_snapshot_usd);
        $this->assertSame(6.5, (float) $item->line_total_usd);
    }

    public function test_invoice_usd_snapshot_does_not_change_when_the_store_rate_changes(): void
    {
        $settings = HomepageSetting::create([
            'usd_to_syp_rate' => 10000,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);
        $product = Product::factory()->create([
            'price' => 1,
            'price_usd' => 10,
            'stock_quantity' => 10,
        ]);
        $province = Province::factory()->create([
            'shipping_fee' => 1,
            'shipping_fee_usd' => 1,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::findOrFail($response->json('data.order_id'));
        $settings->update(['usd_to_syp_rate' => 20000]);

        $order->refresh()->load(['items', 'province', 'coupon']);
        $invoice = (new OrderResource($order))->resolve();

        $this->assertSame(10000.0, (float) $order->exchange_rate_snapshot);
        $this->assertSame(11.0, (float) $order->total_usd);
        $this->assertSame(110000.0, (float) $order->total);
        $this->assertSame(10000.0, $invoice['exchange_rate']['rate']);
        $this->assertSame(11.0, $invoice['amounts']['total']['USD']);
        $this->assertSame(110000.0, $invoice['amounts']['total']['SYP']);
        $this->assertSame(10.0, $invoice['items']->resolve()[0]['amounts']['unit_price']['USD']);
    }

    public function test_legacy_invoice_does_not_invent_an_unknown_usd_value(): void
    {
        $order = Order::factory()->create([
            'subtotal' => 42000,
            'shipping_fee' => 9000,
            'total' => 51000,
            'exchange_rate_snapshot' => null,
            'subtotal_usd' => null,
            'shipping_fee_usd' => null,
            'total_usd' => null,
        ]);

        $order->load(['items', 'province', 'coupon']);
        $invoice = (new OrderResource($order))->resolve();

        $this->assertNull($invoice['exchange_rate']);
        $this->assertSame(51000.0, $invoice['amounts']['total']['SYP']);
        $this->assertNull($invoice['amounts']['total']['USD']);
    }
}
