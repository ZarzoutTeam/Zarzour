<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Discount;
use App\Models\HomepageSetting;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRatePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_syp_price_is_calculated_from_usd_when_an_exchange_rate_exists(): void
    {
        HomepageSetting::create([
            'usd_to_syp_rate' => 13000,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $product = Product::factory()->create([
            'price' => 1,
            'price_usd' => 6.55,
        ]);

        $this->assertSame(85150.0, (float) $product->price_syp);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.price', 85150)
            ->assertJsonPath('data.prices.SYP', 85150)
            ->assertJsonPath('data.prices.USD', 6.55);
    }

    public function test_changing_exchange_rate_reprices_existing_products_with_usd_prices(): void
    {
        $product = Product::factory()->create([
            'price' => 85000,
            'price_usd' => 10,
        ]);
        $legacyProduct = Product::factory()->create([
            'price' => 42000,
            'price_usd' => null,
        ]);

        $settings = HomepageSetting::create([
            'usd_to_syp_rate' => 12000,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $this->assertSame(120000.0, (float) $product->refresh()->price_syp);
        $this->assertSame(42000.0, (float) $legacyProduct->refresh()->price_syp);

        $settings->update(['usd_to_syp_rate' => 13500]);

        $this->assertSame(135000.0, (float) $product->refresh()->price_syp);
        $this->assertSame(42000.0, (float) $legacyProduct->refresh()->price_syp);
    }

    public function test_syp_filters_use_the_recalculated_catalog_price(): void
    {
        $settings = HomepageSetting::create([
            'usd_to_syp_rate' => 10000,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        Product::factory()->create(['price_usd' => 5]);
        $expensiveProduct = Product::factory()->create(['price_usd' => 20]);

        $this->getJson('/api/v1/products?currency=SYP&filter[price_min]=150000')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $expensiveProduct->id)
            ->assertJsonCount(1, 'data.items');

        $settings->update(['usd_to_syp_rate' => 5000]);

        $this->getJson('/api/v1/products?currency=SYP&filter[price_min]=150000')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_exchange_rate_is_exposed_in_public_store_configuration(): void
    {
        HomepageSetting::create([
            'usd_to_syp_rate' => 12750,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $this->getJson('/api/v1/checkout/config')
            ->assertOk()
            ->assertJsonPath('data.exchange_rate.base_currency', 'USD')
            ->assertJsonPath('data.exchange_rate.quote_currency', 'SYP')
            ->assertJsonPath('data.exchange_rate.rate', 12750);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.exchange_rate.rate', 12750);
    }

    public function test_all_current_pricing_definitions_use_usd_as_their_source(): void
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
        $discount = Discount::factory()->create([
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 1,
            'value_usd' => 1,
        ]);
        $coupon = Coupon::factory()->create([
            'code' => 'USD2',
            'type' => 'fixed',
            'value' => 1,
            'value_usd' => 2,
            'min_order_amount' => null,
            'min_order_amount_usd' => 5,
        ]);
        $offer = Offer::factory()->create([
            'product_id' => $product->id,
            'discount_type' => 'fixed',
            'discount_value' => 1,
            'discount_value_usd' => 0.5,
        ]);
        $province = Province::factory()->create([
            'shipping_fee' => 1,
            'shipping_fee_usd' => 1.5,
        ]);

        $this->assertSame(100000.0, (float) $product->price_syp);
        $this->assertSame(10000.0, (float) $discount->value);
        $this->assertSame(20000.0, (float) $coupon->value);
        $this->assertSame(50000.0, (float) $coupon->min_order_amount);
        $this->assertSame(5000.0, (float) $offer->discount_value);
        $this->assertSame(15000.0, (float) $province->shipping_fee);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.discounts.0.value', 10000)
            ->assertJsonPath('data.discounts.0.value_usd', 1)
            ->assertJsonPath('data.discounts.0.values.SYP', 10000)
            ->assertJsonPath('data.discounts.0.values.USD', 1)
            ->assertJsonPath('data.offers.0.discount_value', 5000)
            ->assertJsonPath('data.offers.0.discount_value_usd', 0.5)
            ->assertJsonPath('data.offers.0.discount_values.SYP', 5000)
            ->assertJsonPath('data.offers.0.discount_values.USD', 0.5);

        $this->getJson('/api/v1/provinces')
            ->assertOk()
            ->assertJsonPath('data.0.shipping_fee', 15000)
            ->assertJsonPath('data.0.shipping_fees.SYP', 15000)
            ->assertJsonPath('data.0.shipping_fees.USD', 1.5);

        $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'USD2',
            'province_id' => $province->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 100000)
            ->assertJsonPath('data.lines.0.direct_discount_amount', 10000)
            ->assertJsonPath('data.lines.0.coupon_discount_amount', 20000)
            ->assertJsonPath('data.lines.0.offer_discount_amount', 5000)
            ->assertJsonPath('data.coupon.configured_value', 20000)
            ->assertJsonPath('data.coupon.configured_value_usd', 2)
            ->assertJsonPath('data.coupon.configured_values.SYP', 20000)
            ->assertJsonPath('data.coupon.configured_values.USD', 2)
            ->assertJsonPath('data.coupon.min_order_amount', 50000)
            ->assertJsonPath('data.coupon.min_order_amount_usd', 5)
            ->assertJsonPath('data.shipping_fee', 15000)
            ->assertJsonPath('data.grand_total', 80000);
    }

    public function test_rate_changes_reprice_usd_definitions_without_touching_legacy_or_historical_amounts(): void
    {
        $product = Product::factory()->create(['price' => 99, 'price_usd' => 10]);
        $discount = Discount::factory()->create([
            'product_id' => $product->id,
            'type' => 'fixed',
            'value' => 99,
            'value_usd' => 1,
        ]);
        $offer = Offer::factory()->create([
            'product_id' => $product->id,
            'discount_type' => 'fixed',
            'discount_value' => 99,
            'discount_value_usd' => 0.5,
        ]);
        $fixedCoupon = Coupon::factory()->create([
            'type' => 'fixed',
            'value' => 99,
            'value_usd' => 2,
            'min_order_amount' => 99,
            'min_order_amount_usd' => 5,
        ]);
        $percentageCoupon = Coupon::factory()->create([
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => 99,
            'max_discount_amount_usd' => 3,
        ]);
        $province = Province::factory()->create([
            'shipping_fee' => 99,
            'shipping_fee_usd' => 1.5,
        ]);

        $legacyProduct = Product::factory()->create(['price' => 42000, 'price_usd' => null]);
        $legacyDiscount = Discount::factory()->create([
            'product_id' => $legacyProduct->id,
            'type' => 'fixed',
            'value' => 7000,
            'value_usd' => null,
        ]);
        $legacyCoupon = Coupon::factory()->create([
            'type' => 'fixed',
            'value' => 8000,
            'value_usd' => null,
        ]);
        $legacyProvince = Province::factory()->create([
            'shipping_fee' => 9000,
            'shipping_fee_usd' => null,
        ]);
        $historicalOrder = Order::factory()->create([
            'province_id' => $legacyProvince->id,
            'shipping_fee' => 9000,
            'subtotal' => 42000,
            'total' => 51000,
        ]);

        $settings = HomepageSetting::create([
            'usd_to_syp_rate' => 12000,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $this->assertSame(120000.0, (float) $product->refresh()->price_syp);
        $this->assertSame(12000.0, (float) $discount->refresh()->value);
        $this->assertSame(6000.0, (float) $offer->refresh()->discount_value);
        $this->assertSame(24000.0, (float) $fixedCoupon->refresh()->value);
        $this->assertSame(60000.0, (float) $fixedCoupon->min_order_amount);
        $this->assertSame(36000.0, (float) $percentageCoupon->refresh()->max_discount_amount);
        $this->assertSame(18000.0, (float) $province->refresh()->shipping_fee);

        $settings->update(['usd_to_syp_rate' => 13000]);

        $this->assertSame(130000.0, (float) $product->refresh()->price_syp);
        $this->assertSame(13000.0, (float) $discount->refresh()->value);
        $this->assertSame(6500.0, (float) $offer->refresh()->discount_value);
        $this->assertSame(26000.0, (float) $fixedCoupon->refresh()->value);
        $this->assertSame(65000.0, (float) $fixedCoupon->min_order_amount);
        $this->assertSame(39000.0, (float) $percentageCoupon->refresh()->max_discount_amount);
        $this->assertSame(19500.0, (float) $province->refresh()->shipping_fee);

        $this->assertSame(42000.0, (float) $legacyProduct->refresh()->price_syp);
        $this->assertSame(7000.0, (float) $legacyDiscount->refresh()->value);
        $this->assertSame(8000.0, (float) $legacyCoupon->refresh()->value);
        $this->assertSame(9000.0, (float) $legacyProvince->refresh()->shipping_fee);
        $this->assertSame(9000.0, (float) $historicalOrder->refresh()->shipping_fee);
        $this->assertSame(51000.0, (float) $historicalOrder->total);
    }
}
