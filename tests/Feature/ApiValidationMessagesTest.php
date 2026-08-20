<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_validation_error_is_clear_in_message_and_errors(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'currency' => 'EUR',
        ], ['Accept-Language' => 'ar']);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'عملة الدفع يجب أن تكون SYP أو USD.')
            ->assertJsonPath('errors.currency.0', 'عملة الدفع يجب أن تكون SYP أو USD.');
    }

    public function test_nested_cart_error_explains_that_the_product_is_unavailable(): void
    {
        $response = $this->postJson('/api/v1/cart/calculate', [
            'lines' => [['product_id' => 999999, 'quantity' => 1]],
        ], ['Accept-Language' => 'ar']);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'أحد المنتجات المختارة غير موجود أو لم يعد متاحاً.')
            ->assertJsonPath('errors.lines.0.product_id.0', 'أحد المنتجات المختارة غير موجود أو لم يعد متاحاً.');
    }

    public function test_english_validation_messages_follow_accept_language_header(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali',
            'phone_number' => '123',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], ['Accept-Language' => 'en']);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The phone number must start with 09 and contain exactly 10 digits.')
            ->assertJsonPath('errors.phone_number.0', 'The phone number must start with 09 and contain exactly 10 digits.');
    }

    public function test_authentication_error_explains_that_login_is_required(): void
    {
        $response = $this->getJson('/api/v1/auth/me', [
            'Accept-Language' => 'ar',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', null)
            ->assertJsonPath('message', 'يجب تسجيل الدخول للوصول إلى هذا المحتوى.');
    }
}
