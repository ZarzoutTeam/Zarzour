<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Offer;
use App\Models\OfferGift;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Province;
use App\Support\CatalogCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientDashboardChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_api_exposes_syp_and_usd_prices_while_preserving_legacy_price(): void
    {
        $product = Product::factory()->create([
            'price' => 85000,
            'price_usd' => 6.50,
        ]);

        $response = $this->getJson('/api/v1/products/'.$product->slug);

        $response
            ->assertOk()
            ->assertJsonPath('data.price', 85000)
            ->assertJsonPath('data.prices.SYP', 85000)
            ->assertJsonPath('data.prices.USD', 6.5);
    }

    public function test_product_price_filter_can_target_usd(): void
    {
        Product::factory()->create(['price' => 100000, 'price_usd' => 5]);
        $expected = Product::factory()->create(['price' => 200000, 'price_usd' => 20]);

        $response = $this->getJson('/api/v1/products?currency=USD&filter[price_min]=10');

        $response->assertOk();
        $this->assertSame([$expected->id], collect($response->json('data.items'))->pluck('id')->all());
    }

    public function test_gift_only_offer_adds_a_gift_without_discounting_the_product(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 0]);
        $offer = Offer::factory()->giftOnly()->create(['product_id' => $product->id]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.currency', 'SYP')
            ->assertJsonPath('data.lines.0.offer_discount_amount', 0)
            ->assertJsonPath('data.lines.0.offer_applied', true)
            ->assertJsonPath('data.lines.0.gift.product_id', $giftProduct->id)
            ->assertJsonPath('data.lines.0.final_line_total', 100);
    }

    public function test_unavailable_gift_only_offer_does_not_claim_to_be_applied(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $giftProduct = Product::factory()->outOfStock()->create();
        $offer = Offer::factory()->giftOnly()->create(['product_id' => $product->id]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.lines.0.offer_applied', false)
            ->assertJsonPath('data.lines.0.gift', null)
            ->assertJsonPath('data.lines.0.final_line_total', 100);
    }

    public function test_gift_only_offer_creates_a_free_order_item_without_reducing_total(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 0]);
        $province = Province::factory()->create(['shipping_fee' => 0]);
        $offer = Offer::factory()->giftOnly()->create(['product_id' => $product->id]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'cod',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.currency', 'SYP')
            ->assertJsonPath('data.discount_amount', 0)
            ->assertJsonPath('data.total', 100);

        $giftItem = OrderItem::query()
            ->where('order_id', $response->json('data.order_id'))
            ->where('is_gift', true)
            ->firstOrFail();

        $this->assertSame($giftProduct->id, $giftItem->product_id);
        $this->assertSame(0.0, (float) $giftItem->line_total);
    }

    public function test_homepage_video_and_payment_methods_are_exposed_by_public_api(): void
    {
        Storage::fake('public');

        $settings = HomepageSetting::create([
            'hero_media_type' => 'video',
            'hero_enabled' => true,
            'payment_methods' => ['cod', 'sham_cash'],
        ]);

        $settings
            ->addMedia(UploadedFile::fake()->create('hero.mp4', 500, 'video/mp4'))
            ->toMediaCollection('hero_video');

        $response = $this->getJson('/api/v1/home');

        $response
            ->assertOk()
            ->assertJsonPath('data.hero_media.type', 'video')
            ->assertJsonPath('data.payment_methods.0.key', 'cod')
            ->assertJsonPath('data.payment_methods.1.key', 'sham_cash');

        $this->assertStringContainsString('.mp4', $response->json('data.hero_media.url'));
    }

    public function test_order_validation_uses_payment_methods_enabled_in_dashboard(): void
    {
        HomepageSetting::create([
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
        $province = Province::factory()->create();

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ali',
            'phone_number' => '0911111111',
            'province_id' => $province->id,
            'shipping_address' => 'Damascus',
            'payment_method' => 'sham_cash',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    public function test_new_active_category_invalidates_cached_public_trees_immediately(): void
    {
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/home')->assertOk();

        $category = Category::factory()->create([
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->assertFalse(Cache::has(CatalogCache::ACTIVE_CATEGORIES));
        $this->assertFalse(Cache::has(CatalogCache::HOME_SNAPSHOT));

        $response = $this->getJson('/api/v1/categories');
        $this->assertContains($category->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_product_image_media_change_invalidates_home_snapshot(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        Cache::put(CatalogCache::HOME_SNAPSHOT, ['stale' => true], now()->addMinutes(5));

        $product
            ->addMedia(UploadedFile::fake()->image('product.jpg'))
            ->toMediaCollection('images');

        $this->assertFalse(Cache::has(CatalogCache::HOME_SNAPSHOT));
    }

    public function test_banner_image_media_change_invalidates_banner_and_home_snapshots(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $banner = Banner::factory()->create(['product_id' => $product->id]);

        $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Cache::put(CatalogCache::ACTIVE_BANNERS, ['stale' => true], now()->addMinutes(5));
        Cache::put(CatalogCache::HOME_SNAPSHOT, ['stale' => true], now()->addMinutes(5));

        $banner
            ->addMedia(UploadedFile::fake()->image('banner.jpg'))
            ->toMediaCollection('image');

        $this->assertFalse(Cache::has(CatalogCache::ACTIVE_BANNERS));
        $this->assertFalse(Cache::has(CatalogCache::HOME_SNAPSHOT));

        $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonPath('data.0.id', $banner->id);
    }
}
