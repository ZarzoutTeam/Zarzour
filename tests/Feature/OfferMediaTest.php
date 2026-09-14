<?php

namespace Tests\Feature;

use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Models\Offer;
use App\Models\OfferGift;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogCache;
use Filament\Facades\Filament;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Tests\TestCase;

class OfferMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
    }

    public function test_custom_offer_and_gift_images_are_exposed_across_catalog_and_cart_contracts(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $giftProduct = Product::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 0]);
        $offer = Offer::factory()->giftOnly()->create(['product_id' => $product->id]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $offer
            ->addMedia(UploadedFile::fake()->image('offer-campaign.jpg', 1600, 900))
            ->toMediaCollection(Offer::MEDIA_OFFER_IMAGE);
        $offer
            ->addMedia(UploadedFile::fake()->image('gift-campaign.jpg', 1000, 1000))
            ->toMediaCollection(Offer::MEDIA_GIFT_IMAGE);

        $detail = $this->getJson('/api/v1/products/'.$product->slug)->assertOk();
        $list = $this->getJson('/api/v1/products')->assertOk();
        $home = $this->getJson('/api/v1/home')->assertOk();
        $cart = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $this->assertStringContainsString('offer-campaign.jpg', $detail->json('data.offers.0.image.original_url'));
        $this->assertStringContainsString('gift-campaign.jpg', $detail->json('data.offers.0.gift.image.original_url'));
        $this->assertStringContainsString('offer-campaign.jpg', $list->json('data.items.0.offer.image.original_url'));
        $this->assertStringContainsString('offer-campaign.jpg', $home->json('data.offered_products.0.offer.image.original_url'));
        $this->assertStringContainsString('gift-campaign.jpg', $cart->json('data.lines.0.gift.image.original_url'));
        $this->assertSame($giftProduct->slug, $detail->json('data.offers.0.gift.slug'));
        $this->assertSame('/products/'.$giftProduct->slug, $detail->json('data.offers.0.gift.product_path'));
        $this->assertSame('/products/'.$giftProduct->slug, $list->json('data.items.0.offer.gift.product_path'));
        $this->assertSame('/products/'.$giftProduct->slug, $home->json('data.offered_products.0.offer.gift.product_path'));

        $this->assertSame(
            $detail->json('data.offers.0.image.original_url'),
            $detail->json('data.offers.0.image.large_url'),
        );
        $this->assertTrue($detail->json('data.offers.0.gift.available'));
    }

    public function test_offer_media_falls_back_to_the_related_product_images(): void
    {
        $product = Product::factory()->create();
        $giftProduct = Product::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 0]);
        $offer = Offer::factory()->withGift()->create(['product_id' => $product->id]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $product
            ->addMedia(UploadedFile::fake()->image('product-primary.jpg'))
            ->toMediaCollection('images');
        $giftProduct
            ->addMedia(UploadedFile::fake()->image('gift-product-primary.jpg'))
            ->toMediaCollection('images');

        $response = $this->getJson('/api/v1/products/'.$product->slug)->assertOk();
        $cart = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $this->assertStringContainsString('product-primary.jpg', $response->json('data.offers.0.image.original_url'));
        $this->assertStringContainsString('gift-product-primary.jpg', $response->json('data.offers.0.gift.image.original_url'));
        $this->assertStringContainsString('gift-product-primary.jpg', $cart->json('data.lines.0.gift.image.original_url'));
    }

    public function test_inactive_gift_product_does_not_expose_a_broken_storefront_path(): void
    {
        $product = Product::factory()->create();
        $giftProduct = Product::factory()->create([
            'is_active' => false,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
        ]);
        $offer = Offer::factory()->giftOnly()->create(['product_id' => $product->id]);
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);

        $response = $this->getJson('/api/v1/products/'.$product->slug)->assertOk();

        $response
            ->assertJsonPath('data.offers.0.gift.slug', $giftProduct->slug)
            ->assertJsonPath('data.offers.0.gift.product_path', null)
            ->assertJsonPath('data.offers.0.gift.available', false);
    }

    public function test_offer_media_is_single_file_and_invalidates_the_home_snapshot(): void
    {
        $offer = Offer::factory()->create();

        Cache::put(CatalogCache::HOME_SNAPSHOT, ['stale' => true], now()->addMinutes(5));

        $offer
            ->addMedia(UploadedFile::fake()->image('first-offer.jpg'))
            ->toMediaCollection(Offer::MEDIA_OFFER_IMAGE);
        $offer
            ->addMedia(UploadedFile::fake()->image('replacement-offer.jpg'))
            ->toMediaCollection(Offer::MEDIA_OFFER_IMAGE);

        $this->assertFalse(Cache::has(CatalogCache::HOME_SNAPSHOT));
        $this->assertCount(1, $offer->fresh()->getMedia(Offer::MEDIA_OFFER_IMAGE));
        $this->assertSame('replacement-offer', $offer->fresh()->getFirstMedia(Offer::MEDIA_OFFER_IMAGE)?->name);

        $media = $offer->fresh()->getFirstMedia(Offer::MEDIA_OFFER_IMAGE);
        $conversions = ConversionCollection::createForMedia($media);

        foreach (['thumbnail', 'medium', 'large'] as $conversion) {
            $this->assertTrue($conversions->getByName($conversion)->shouldBeQueued());
        }
    }

    public function test_gift_image_is_removed_when_offer_no_longer_has_a_gift(): void
    {
        $this->authenticateAdmin();

        $offer = Offer::factory()->withGift()->create();
        $giftProduct = Product::factory()->create();
        OfferGift::create(['offer_id' => $offer->id, 'gift_product_id' => $giftProduct->id]);
        $offer
            ->addMedia(UploadedFile::fake()->image('gift.jpg'))
            ->toMediaCollection(Offer::MEDIA_GIFT_IMAGE);

        Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()])
            ->fillForm(['type' => 'discount_only'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($offer->fresh()->hasMedia(Offer::MEDIA_GIFT_IMAGE));
        $this->assertDatabaseMissing('offer_gifts', ['offer_id' => $offer->id]);
    }

    public function test_offer_form_saves_both_image_fields_with_the_large_conversion(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create();
        $giftProduct = Product::factory()->create();
        $component = Livewire::test(CreateOffer::class);

        foreach ([Offer::MEDIA_OFFER_IMAGE, Offer::MEDIA_GIFT_IMAGE] as $fieldName) {
            $component->assertFormFieldExists(
                $fieldName,
                static fn ($field): bool => $field instanceof SpatieMediaLibraryFileUpload
                    && $field->getConversion() === 'large',
            );
        }

        $component
            ->fillForm([
                'product_id' => $product->id,
                'type' => 'gift_only',
                'gift_product_id' => $giftProduct->id,
                'offer_image' => [
                    'offer-upload' => UploadedFile::fake()->image('offer-upload.jpg', 1600, 900),
                ],
                'gift_image' => [
                    'gift-upload' => UploadedFile::fake()->image('gift-upload.jpg', 1000, 1000),
                ],
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('offer-upload', $offer->getFirstMedia(Offer::MEDIA_OFFER_IMAGE)?->name);
        $this->assertSame('gift-upload', $offer->getFirstMedia(Offer::MEDIA_GIFT_IMAGE)?->name);
    }

    private function authenticateAdmin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }
}
