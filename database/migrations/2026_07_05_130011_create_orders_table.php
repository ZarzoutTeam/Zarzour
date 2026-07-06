<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('phone_number');
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->text('shipping_address');
            $table->text('extra_notes')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2);
            $table->decimal('total', 10, 2);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->foreignId('applied_offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->enum('payment_method', ['cod', 'sham_cash', 'visa_ui']);
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'expired'])->default('pending');
            $table->timestamp('reserved_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
