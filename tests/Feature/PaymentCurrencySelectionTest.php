<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCurrencySelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_preview_returns_the_amount_to_pay_in_the_selected_currency(): void
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
        $province = Province::factory()->create([
            'shipping_fee' => 1,
            'shipping_fee_usd' => 2,
        ]);

        $this->postJson('/api/v1/cart/calculate', [
            'currency' => 'usd',
            'province_id' => $province->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.payment.currency', 'USD')
            ->assertJsonPath('data.payment.subtotal', 20)
            ->assertJsonPath('data.payment.shipping_fee', 2)
            ->assertJsonPath('data.payment.total', 22)
            ->assertJsonPath('data.amounts.grand_total.SYP', 220000)
            ->assertJsonPath('data.amounts.grand_total.USD', 22);
    }

    public function test_payment_currency_defaults_to_syp_for_existing_clients(): void
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

        $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('data.currency', 'SYP')
            ->assertJsonPath('data.payment.currency', 'SYP')
            ->assertJsonPath('data.payment.total', 100000);
    }

    public function test_unsupported_payment_currency_is_rejected(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/v1/cart/calculate', [
            'currency' => 'EUR',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_usd_payment_is_rejected_when_no_exchange_rate_is_configured(): void
    {
        $product = Product::factory()->create([
            'price' => 100000,
            'price_usd' => null,
        ]);

        $this->postJson('/api/v1/cart/calculate', [
            'currency' => 'USD',
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_checkout_config_exposes_supported_payment_currencies(): void
    {
        $this->getJson('/api/v1/checkout/config')
            ->assertOk()
            ->assertJsonPath('data.default_currency', 'SYP')
            ->assertJsonPath('data.currencies.0.key', 'SYP')
            ->assertJsonPath('data.currencies.1.key', 'USD');
    }
}
