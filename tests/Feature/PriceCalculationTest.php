<?php

namespace Tests\Feature;

use App\Exceptions\OverlappingDiscountException;
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

    public function test_coupon_minimum_order_is_checked_after_direct_product_discounts(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Discount::factory()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 10,
        ]);
        Coupon::factory()->create([
            'code' => 'MIN95',
            'type' => 'fixed',
            'value' => 5,
            'min_order_amount' => 95,
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'MIN95',
        ]);

        $response->assertStatus(422);
        $this->assertSame('coupon_below_minimum', $response->json('errors.code'));
    }

    public function test_fixed_discount_scopes_are_applied_with_the_expected_quantity_rules(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Discount::factory()->create([
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 10,
        ]);
        Coupon::factory()->create([
            'code' => 'CART25',
            'type' => 'fixed',
            'value' => 25,
        ]);
        Offer::factory()->create([
            'product_id' => $product->id,
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 3]],
            'coupon_code' => 'CART25',
        ]);

        $response->assertOk();
        $line = $response->json('data.lines.0');

        // Product and offer fixed discounts are per item; a fixed coupon is once per cart.
        $this->assertSame(30.0, (float) $line['direct_discount_amount']);
        $this->assertSame(25.0, (float) $line['coupon_discount_amount']);
        $this->assertSame(15.0, (float) $line['offer_discount_amount']);
        $this->assertSame(230.0, (float) $line['final_line_total']);
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

    public function test_percentage_coupon_discount_does_not_exceed_its_maximum_amount(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        Coupon::factory()->create([
            'code' => 'CAPPED25',
            'type' => 'percentage',
            'value' => 25,
            'max_discount_amount' => 100,
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'CAPPED25',
        ]);

        $response->assertOk();
        $this->assertSame(100.0, (float) $response->json('data.coupon.discount_amount'));
        $this->assertSame(100.0, (float) $response->json('data.coupon.max_discount_amount'));
        $this->assertSame(100.0, (float) $response->json('data.lines.0.coupon_discount_amount'));
        $this->assertSame(900.0, (float) $response->json('data.total_before_shipping'));
    }

    public function test_coupon_allocation_rounding_preserves_the_exact_cart_discount(): void
    {
        $category = \App\Models\Category::factory()->create();
        $products = Product::factory()->count(100)->create([
            'category_id' => $category->id,
            'price' => 0.01,
        ]);
        Coupon::factory()->create([
            'code' => 'HALF',
            'type' => 'percentage',
            'value' => 50,
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => $products->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->all(),
            'coupon_code' => 'HALF',
        ]);

        $response->assertOk();
        $lineDiscounts = array_column($response->json('data.lines'), 'coupon_discount_amount');

        $this->assertSame(0.5, round(array_sum($lineDiscounts), 2));
        $this->assertSame(0.5, (float) $response->json('data.coupon.discount_amount'));
        $this->assertSame(0.5, (float) $response->json('data.total_before_shipping'));
        $this->assertTrue(collect($lineDiscounts)->every(fn ($amount) => in_array((float) $amount, [0.0, 0.01], true)));
    }

    public function test_duplicate_product_lines_are_rejected_to_prevent_duplicate_offer_gifts(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('lines.1.product_id');
    }

    public function test_coupon_code_lookup_is_trimmed_and_case_insensitive(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $coupon = Coupon::factory()->create([
            'code' => 'save10',
            'type' => 'fixed',
            'value' => 10,
        ]);

        $this->assertSame('SAVE10', $coupon->code);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => '  save10  ',
        ]);

        $response->assertOk();
        $this->assertSame(90.0, (float) $response->json('data.total_before_shipping'));
    }

    public function test_per_customer_limited_coupon_requires_a_phone_number_for_preview(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Coupon::factory()->create([
            'code' => 'ONCE-PER-PHONE',
            'type' => 'fixed',
            'value' => 10,
            'per_customer_usage_limit' => 1,
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'ONCE-PER-PHONE',
        ]);

        $response->assertStatus(422);
        $this->assertSame(__('http.coupon_phone_required'), $response->json('message'));
        $this->assertSame('coupon_phone_required', $response->json('errors.code'));
    }

    public function test_per_customer_coupon_limit_is_enforced_by_order_phone_number(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
        $province = Province::factory()->create(['shipping_fee' => 0]);
        $coupon = Coupon::factory()->create([
            'code' => 'PHONE-ONCE',
            'type' => 'fixed',
            'value' => 10,
            'usage_limit' => 10,
            'per_customer_usage_limit' => 1,
        ]);

        $payload = [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'coupon_code' => 'phone-once',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $first = $this->postJson('/api/v1/orders', $payload);
        $first->assertCreated();
        $this->assertSame(1, $first->json('data.coupon.customer_usage_count'));
        $this->assertSame(0, $first->json('data.coupon.customer_remaining_uses'));

        $second = $this->postJson('/api/v1/orders', $payload);
        $second->assertStatus(422);
        $this->assertSame(__('http.coupon_customer_usage_limit_reached'), $second->json('message'));
        $this->assertSame('coupon_customer_usage_limit_reached', $second->json('errors.code'));

        $otherCustomer = $this->postJson('/api/v1/orders', [
            ...$payload,
            'phone_number' => '0922222222',
        ]);
        $otherCustomer->assertCreated();

        $this->assertSame(2, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_cancelled_orders_still_count_toward_the_per_customer_coupon_limit(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $coupon = Coupon::factory()->create([
            'code' => 'NO-REUSE',
            'type' => 'fixed',
            'value' => 10,
            'per_customer_usage_limit' => 1,
        ]);
        \App\Models\Order::factory()->create([
            'coupon_id' => $coupon->id,
            'phone_number' => '0911111111',
            'status' => 'cancelled',
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'NO-REUSE',
            'phone_number' => '0911111111',
        ]);

        $response->assertStatus(422);
        $this->assertSame(__('http.coupon_customer_usage_limit_reached'), $response->json('message'));
    }

    public function test_general_coupon_ignores_a_stale_specific_phone_number(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        Coupon::factory()->create([
            'code' => 'GENERAL10',
            'type' => 'fixed',
            'value' => 10,
            'scope' => 'general',
            'phone_number' => '0911111111',
        ]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'GENERAL10',
            'phone_number' => '0922222222',
        ]);

        $response->assertOk();
        $this->assertSame(90.0, (float) $response->json('data.total_before_shipping'));
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

    public function test_activating_second_overlapping_direct_discount_on_same_product_is_rejected(): void
    {
        $product = Product::factory()->create();
        Discount::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->expectException(OverlappingDiscountException::class);

        Discount::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);
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
        $this->assertSame(45.0, (float) $orderResponse->json('data.coupon_discount_amount'));
        $this->assertSame(1130.0, (float) $orderResponse->json('data.total'));

        $this->assertDatabaseCount('order_items', 2);
        $giftItems = OrderItem::where('order_id', $orderId)->where('is_gift', true)->get();
        $this->assertCount(1, $giftItems);
        $this->assertSame(0.0, (float) $giftItems->first()->unit_price_snapshot);
        $this->assertSame(0.0, (float) $giftItems->first()->line_total);
        $this->assertSame($giftProduct->id, $giftItems->first()->product_id);

        $paidItem = OrderItem::where('order_id', $orderId)->where('is_gift', false)->firstOrFail();
        $this->assertSame(20.0, (float) $paidItem->direct_discount_amount);
        $this->assertSame(45.0, (float) $paidItem->coupon_discount_amount);
        $this->assertSame(5.0, (float) $paidItem->offer_discount_amount);
        $this->assertSame($offer->id, $paidItem->offer_id);
    }
}
