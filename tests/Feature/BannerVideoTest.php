<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Product;
use App\Support\CatalogCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_video_banner_is_exposed_by_banner_and_home_apis(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $banner = Banner::factory()->create([
            'product_id' => $product->id,
            'media_type' => 'video',
        ]);

        $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $banner
            ->addMedia(UploadedFile::fake()->create('banner.mp4', 1024, 'video/mp4'))
            ->toMediaCollection('video');

        $this->assertFalse(Cache::has(CatalogCache::ACTIVE_BANNERS));

        $response = $this->getJson('/api/v1/banners');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $banner->id)
            ->assertJsonPath('data.0.media_type', 'video')
            ->assertJsonPath('data.0.media.type', 'video')
            ->assertJsonPath('data.0.media.thumbnail_url', null)
            ->assertJsonPath('data.0.image.hero', '')
            ->assertJsonPath('data.0.image.thumbnail', '');

        $this->assertStringEndsWith('.mp4', $response->json('data.0.media.url'));
        $this->assertSame($response->json('data.0.media.url'), $response->json('data.0.video_url'));

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.banners.0.id', $banner->id)
            ->assertJsonPath('data.banners.0.media_type', 'video');
    }

    public function test_banner_keeps_only_the_selected_media_type(): void
    {
        Storage::fake('public');

        $banner = Banner::factory()->create(['media_type' => 'image']);
        $banner
            ->addMedia(UploadedFile::fake()->image('banner.jpg'))
            ->toMediaCollection('image');
        $banner
            ->addMedia(UploadedFile::fake()->create('banner.mp4', 1024, 'video/mp4'))
            ->toMediaCollection('video');

        $banner->update(['media_type' => 'video']);
        $banner->clearInactiveMedia();

        $this->assertFalse($banner->hasMedia('image'));
        $this->assertTrue($banner->hasMedia('video'));
    }
}
