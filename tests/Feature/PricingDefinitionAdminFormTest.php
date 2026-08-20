<?php

namespace Tests\Feature;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Discounts\Pages\CreateDiscount;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Provinces\Pages\CreateProvince;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingDefinitionAdminFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_fixed_coupon_can_be_created_with_usd_as_its_source(): void
    {
        $this->createSettingsWithRate(13520);

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'FIXED10',
                'type' => 'fixed',
                'value_usd' => 10,
                'scope' => 'general',
                'min_order_amount_usd' => 100,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('coupons', [
            'code' => 'FIXED10',
            'value_usd' => 10,
            'value' => 135200,
            'min_order_amount_usd' => 100,
            'min_order_amount' => 1352000,
        ]);
    }

    public function test_fixed_offer_can_be_created_with_usd_as_its_source(): void
    {
        $this->createSettingsWithRate(13520);
        $product = Product::factory()->create();

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'product_id' => $product->id,
                'type' => 'discount_only',
                'discount_type' => 'fixed',
                'discount_value_usd' => 5,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offers', [
            'product_id' => $product->id,
            'discount_type' => 'fixed',
            'discount_value_usd' => 5,
            'discount_value' => 67600,
        ]);
    }

    public function test_fixed_product_discount_can_be_created_with_usd_as_its_source(): void
    {
        $this->createSettingsWithRate(13520);
        $product = Product::factory()->create();

        Livewire::test(CreateDiscount::class)
            ->fillForm([
                'product_id' => $product->id,
                'type' => 'fixed',
                'value_usd' => 2,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('discounts', [
            'product_id' => $product->id,
            'type' => 'fixed',
            'value_usd' => 2,
            'value' => 27040,
        ]);
    }

    public function test_shipping_fee_can_be_created_with_usd_as_its_source(): void
    {
        $this->createSettingsWithRate(13520);

        Livewire::test(CreateProvince::class)
            ->fillForm([
                'name' => 'ريف دمشق',
                'shipping_fee_usd' => 1.5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('provinces', [
            'name' => 'ريف دمشق',
            'shipping_fee_usd' => 1.5,
            'shipping_fee' => 20280,
        ]);
    }

    public function test_usd_pricing_form_reports_a_validation_error_when_rate_is_missing(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'NO-RATE',
                'type' => 'fixed',
                'value_usd' => 10,
                'scope' => 'general',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['value_usd']);
    }

    public function test_percentage_coupon_does_not_require_an_exchange_rate(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'PERCENT10',
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'general',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('coupons', [
            'code' => 'PERCENT10',
            'type' => 'percentage',
            'value' => 10,
            'value_usd' => null,
        ]);
    }

    public function test_gift_only_offer_does_not_require_an_exchange_rate(): void
    {
        $product = Product::factory()->create();
        $giftProduct = Product::factory()->create();

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'product_id' => $product->id,
                'type' => 'gift_only',
                'gift_product_id' => $giftProduct->id,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offers', [
            'product_id' => $product->id,
            'type' => 'gift_only',
            'discount_type' => null,
            'discount_value' => null,
            'discount_value_usd' => null,
        ]);
        $this->assertDatabaseHas('offer_gifts', [
            'gift_product_id' => $giftProduct->id,
        ]);
    }

    private function createSettingsWithRate(float $rate): void
    {
        HomepageSetting::create([
            'usd_to_syp_rate' => $rate,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);
    }
}
