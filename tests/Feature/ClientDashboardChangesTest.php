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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Tests\TestCase;

class ClientDashboardChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_and_media_library_allow_the_configured_field_upload_limits(): void
    {
        $temporaryUploadMaxSize = max(
            (int) config('catalog.media.max_image_size_kb'),
            (int) config('catalog.media.max_video_size_kb'),
        );

        $this->assertContains(
            'max:'.$temporaryUploadMaxSize,
            config('livewire.temporary_file_upload.rules'),
        );
        $this->assertSame($temporaryUploadMaxSize * 1024, config('media-library.max_file_size'));
    }

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

    public function test_product_api_exposes_uploaded_video_url(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $product
            ->addMedia(UploadedFile::fake()->create('product.mp4', 1024, 'video/mp4'))
            ->toMediaCollection('video');

        $this->assertSame('public', $product->getFirstMedia('video')?->disk);

        $response = $this->getJson('/api/v1/products/'.$product->slug);

        $response
            ->assertOk()
            ->assertJsonPath('data.media.0.collection', 'video')
            ->assertJsonPath('data.media.0.original_url', null)
            ->assertJsonPath('data.media.0.thumbnail_url', null)
            ->assertJsonPath('data.media.0.medium_url', null)
            ->assertJsonPath('data.media.0.large_url', null);

        $this->assertStringEndsWith('.mp4', $response->json('data.media.0.url'));
    }

    public function test_product_api_prefers_high_quality_webp_and_preserves_original_image_url(): void
    {
        Storage::fake('public');
        config()->set('media-library.queue_conversions_after_database_commit', false);

        $product = Product::factory()->create();
        $media = $product
            ->addMedia(UploadedFile::fake()->image('product.jpg', 3000, 2000))
            ->toMediaCollection('images');

        $this->assertTrue($media->hasGeneratedConversion('thumbnail'));
        $this->assertTrue($media->hasGeneratedConversion('medium'));
        $this->assertTrue($media->hasGeneratedConversion('large'));

        $conversions = ConversionCollection::createForMedia($media);
        $this->assertTrue($conversions->getByName('thumbnail')->shouldBeQueued());
        $this->assertTrue($conversions->getByName('medium')->shouldBeQueued());
        $this->assertTrue($conversions->getByName('large')->shouldBeQueued());

        $this->assertSame([400, 267], array_slice(getimagesize($media->getPath('thumbnail')), 0, 2));
        $this->assertSame([1600, 1067], array_slice(getimagesize($media->getPath('medium')), 0, 2));
        $this->assertSame([2560, 1707], array_slice(getimagesize($media->getPath('large')), 0, 2));

        $response = $this->getJson('/api/v1/products/'.$product->slug);

        $response
            ->assertOk()
            ->assertJsonPath('data.media.0.collection', 'images');

        $this->assertStringEndsWith('.jpg', $response->json('data.media.0.original_url'));
        $this->assertStringEndsWith('-thumbnail.webp', $response->json('data.media.0.thumbnail_url'));
        $this->assertStringEndsWith('-medium.webp', $response->json('data.media.0.medium_url'));
        $this->assertStringEndsWith('-large.webp', $response->json('data.media.0.large_url'));
        $this->assertSame($response->json('data.media.0.large_url'), $response->json('data.media.0.url'));
    }

    public function test_product_image_remains_visible_while_queued_conversions_are_pending(): void
    {
        Storage::fake('public');
        Queue::fake();
        config()->set('media-library.queue_conversions_after_database_commit', false);

        $product = Product::factory()->create();
        $media = $product
            ->addMedia(UploadedFile::fake()->image('pending.jpg', 1600, 1000))
            ->toMediaCollection('images');

        $this->assertFalse($media->hasGeneratedConversion('large'));

        $response = $this->getJson('/api/v1/products/'.$product->slug)->assertOk();
        $originalUrl = $response->json('data.media.0.original_url');

        $this->assertStringEndsWith('.jpg', $originalUrl);
        $this->assertSame($originalUrl, $response->json('data.media.0.url'));
        $this->assertSame($originalUrl, $response->json('data.media.0.thumbnail_url'));
        $this->assertSame($originalUrl, $response->json('data.media.0.medium_url'));
        $this->assertSame($originalUrl, $response->json('data.media.0.large_url'));
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

    public function test_category_image_is_exposed_and_invalidates_category_caches(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create([
            'parent_id' => null,
            'is_active' => true,
        ]);

        Cache::put(CatalogCache::ACTIVE_CATEGORIES, ['stale' => true], now()->addMinutes(5));
        Cache::put(CatalogCache::HOME_SNAPSHOT, ['stale' => true], now()->addMinutes(5));

        $category
            ->addMedia(UploadedFile::fake()->image('category.jpg'))
            ->toMediaCollection('image');

        $this->assertFalse(Cache::has(CatalogCache::ACTIVE_CATEGORIES));
        $this->assertFalse(Cache::has(CatalogCache::HOME_SNAPSHOT));

        $response = $this->getJson('/api/v1/categories');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->id);

        $this->assertStringContainsString('category', $response->json('data.0.image.medium'));
        $this->assertStringContainsString('category', $response->json('data.0.image.thumbnail'));
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
