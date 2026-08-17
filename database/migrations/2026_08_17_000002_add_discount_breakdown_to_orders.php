<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->decimal('value', 14, 2)->change();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->decimal('discount_value', 14, 2)->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 14, 2)->change();
            $table->decimal('discount_amount', 14, 2)->default(0)->change();
            $table->decimal('shipping_fee', 14, 2)->change();
            $table->decimal('total', 14, 2)->change();
            $table->decimal('coupon_discount_amount', 14, 2)->nullable()->after('discount_amount');
            $table->index(['coupon_id', 'phone_number'], 'orders_coupon_phone_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price_snapshot', 14, 2)->change();
            $table->decimal('line_total', 14, 2)->change();
            $table->decimal('direct_discount_amount', 14, 2)->nullable()->after('line_total');
            $table->decimal('coupon_discount_amount', 14, 2)->nullable()->after('direct_discount_amount');
            $table->decimal('offer_discount_amount', 14, 2)->nullable()->after('coupon_discount_amount');
        });

        // A zero coupon discount is known only for legacy orders that never used
        // a coupon. Historical coupon/item breakdowns cannot be reconstructed
        // reliably, so they intentionally remain null instead of inventing data.
        DB::table('orders')->whereNull('coupon_id')->update(['coupon_discount_amount' => 0]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'direct_discount_amount',
                'coupon_discount_amount',
                'offer_discount_amount',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_coupon_phone_index');
            $table->dropColumn('coupon_discount_amount');
            $table->decimal('subtotal', 10, 2)->change();
            $table->decimal('discount_amount', 10, 2)->default(0)->change();
            $table->decimal('shipping_fee', 10, 2)->change();
            $table->decimal('total', 10, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price_snapshot', 10, 2)->change();
            $table->decimal('line_total', 10, 2)->change();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->decimal('discount_value', 10, 2)->nullable()->change();
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->decimal('value', 10, 2)->change();
        });
    }
};
