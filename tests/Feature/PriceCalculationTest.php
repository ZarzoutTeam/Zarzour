<?php

namespace Tests\Feature;

use App\Exceptions\OverlappingOfferException;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Offer;
use App\Models\OfferGift;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_discount_alone_reduces_line_total(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Discount::factory()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $line = $response->json('data.lines.0');

        $this->assertSame(100.0, (float) $line['original_line_total']);
        $this->assertSame(10.0, (float) $line['direct_discount_amount']);
        $this->assertSame(90.0, (float) $line['final_line_total']);
        $this->assertSame(90.0, (float) $response->json('data.total_before_shipping'));
    }

    public function test_coupon_applies_after_direct_discount_with_exact_final_number(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Discount::factory()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);
        Coupon::factory()->create(['code' => 'SPORT25', 'type' => 'percentage', 'value' => 25]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'SPORT25',
        ]);

        $response->assertOk();
        $line = $response->json('data.lines.0');

        // 100 -10% direct = 90, then -25% coupon on 90 = 22.5 -> 67.5
        $this->assertSame(10.0, (float) $line['direct_discount_amount']);
        $this->assertSame(22.5, (float) $line['coupon_discount_amount']);
        $this->assertSame(67.5, (float) $line['final_line_total']);
        $this->assertSame(67.5, (float) $response->json('data.total_before_shipping'));
    }

    public function test_customer_specific_coupon_succeeds_only_for_matching_phone(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Coupon::factory()->forUser('0911111111')->create(['code' => 'VIP10', 'type' => 'fixed', 'value' => 10]);

        $matching = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'VIP10',
            'phone_number' => '0911111111',
        ]);
        $matching->assertOk();
        $this->assertSame(90.0, (float) $matching->json('data.total_before_shipping'));

        $mismatched = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'VIP10',
            'phone_number' => '0922222222',
        ]);
        $mismatched->assertStatus(422);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Coupon::factory()->create(['code' => 'OLD10', 'expires_at' => now()->subDay()]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'OLD10',
        ]);

        $response->assertStatus(422);
        $this->assertSame(false, $response->json('success'));
    }

    public function test_coupon_at_usage_limit_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Coupon::factory()->create(['code' => 'MAXED', 'usage_limit' => 3, 'used_count' => 3]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'MAXED',
        ]);

        $response->assertStatus(422);
    }

    public function test_coupon_usage_count_increments_only_on_order_creation_not_on_preview(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
        $province = Province::factory()->create(['shipping_fee' => 500]);
        $coupon = Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 5, 'used_count' => 0]);

        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/cart/calculate', [
                'lines' => [['product_id' => $product->id, 'quantity' => 1]],
                'coupon_code' => 'ONCE',
            ])->assertOk();
        }

        $this->assertSame(0, $coupon->fresh()->used_count);

        $orderResponse = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'coupon_code' => 'ONCE',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $orderResponse->assertCreated();
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_active_offer_with_available_gift_stock_returns_gift_in_calculation(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 0]);
        $offer = Offer::factory()->withGift()->create(['product_id' => $product->id, 'discount_type' => 'fixed', 'discount_value' => 5]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $gift = $response->json('data.lines.0.gift');

        $this->assertNotNull($gift);
        $this->assertSame($giftProduct->id, $gift['product_id']);
    }

    public function test_offer_with_out_of_stock_gift_returns_null_gift_but_still_applies_discount(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 2, 'reserved_quantity' => 2]); // available = 0
        $offer = Offer::factory()->withGift()->create(['product_id' => $product->id, 'discount_type' => 'fixed', 'discount_value' => 5]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $line = $response->json('data.lines.0');

        $this->assertNull($line['gift']);
        $this->assertSame(5.0, (float) $line['offer_discount_amount']);
        $this->assertSame(95.0, (float) $line['final_line_total']);
    }

    public function test_activating_second_overlapping_offer_on_same_product_is_rejected(): void
    {
        $product = Product::factory()->create();
        Offer::factory()->create(['product_id' => $product->id, 'is_active' => true, 'starts_at' => null, 'ends_at' => null]);

        $this->expectException(OverlappingOfferException::class);

        Offer::factory()->create(['product_id' => $product->id, 'is_active' => true, 'starts_at' => null, 'ends_at' => null]);
    }

    public function test_order_succeeds_without_gift_item_when_gift_stock_runs_out_between_preview_and_creation(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 1, 'reserved_quantity' => 0]);
        $offer = Offer::factory()->withGift()->create(['product_id' => $product->id, 'discount_type' => 'fixed', 'discount_value' => 5]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);
        $province = Province::factory()->create(['shipping_fee' => 0]);

        // Preview: gift is available.
        $preview = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $this->assertNotNull($preview->json('data.lines.0.gift'));

        // Gift stock disappears before the customer actually checks out.
        $giftProduct->update(['reserved_quantity' => 1]);

        $orderResponse = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $orderResponse->assertCreated();
        $orderId = $orderResponse->json('data.order_id');

        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(0, OrderItem::where('order_id', $orderId)->where('is_gift', true)->count());
    }

    public function test_full_stacking_direct_discount_coupon_and_gift_together(): void
    {
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 0]);
        Discount::factory()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);
        Coupon::factory()->create(['code' => 'SPORT25', 'type' => 'percentage', 'value' => 25]);
        $offer = Offer::factory()->withGift()->create(['product_id' => $product->id, 'discount_type' => 'fixed', 'discount_value' => 5]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);
        $province = Province::factory()->create(['shipping_fee' => 1000]);

        // 200 -10% = 180, -25% coupon (of 180) = 45 -> 135, -5 offer fixed -> 130
        $orderResponse = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'coupon_code' => 'SPORT25',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $orderResponse->assertCreated();
        $orderId = $orderResponse->json('data.order_id');

        $this->assertSame(200.0, (float) $orderResponse->json('data.subtotal'));
        $this->assertSame(70.0, (float) $orderResponse->json('data.discount_amount'));
        $this->assertSame(1130.0, (float) $orderResponse->json('data.total'));

        $this->assertDatabaseCount('order_items', 2);
        $giftItems = OrderItem::where('order_id', $orderId)->where('is_gift', true)->get();
        $this->assertCount(1, $giftItems);
        $this->assertSame(0.0, (float) $giftItems->first()->unit_price_snapshot);
        $this->assertSame(0.0, (float) $giftItems->first()->line_total);
        $this->assertSame($giftProduct->id, $giftItems->first()->product_id);
    }
}
