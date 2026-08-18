<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Product;
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
}
