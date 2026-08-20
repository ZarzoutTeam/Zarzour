<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('exchange_rate_snapshot', 14, 2)->nullable()->after('currency');
            $table->decimal('subtotal_usd', 14, 2)->nullable()->after('subtotal');
            $table->decimal('discount_amount_usd', 14, 2)->nullable()->after('discount_amount');
            $table->decimal('coupon_discount_amount_usd', 14, 2)->nullable()->after('coupon_discount_amount');
            $table->decimal('shipping_fee_usd', 14, 2)->nullable()->after('shipping_fee');
            $table->decimal('total_usd', 14, 2)->nullable()->after('total');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price_snapshot_usd', 14, 2)->nullable()->after('unit_price_snapshot');
            $table->decimal('line_total_usd', 14, 2)->nullable()->after('line_total');
            $table->decimal('direct_discount_amount_usd', 14, 2)->nullable()->after('direct_discount_amount');
            $table->decimal('coupon_discount_amount_usd', 14, 2)->nullable()->after('coupon_discount_amount');
            $table->decimal('offer_discount_amount_usd', 14, 2)->nullable()->after('offer_discount_amount');
        });

        // Existing orders intentionally remain null in USD. Their historical
        // exchange rate is unknown, so backfilling with today's rate would alter
        // the financial meaning of an old invoice.
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price_snapshot_usd',
                'line_total_usd',
                'direct_discount_amount_usd',
                'coupon_discount_amount_usd',
                'offer_discount_amount_usd',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'exchange_rate_snapshot',
                'subtotal_usd',
                'discount_amount_usd',
                'coupon_discount_amount_usd',
                'shipping_fee_usd',
                'total_usd',
            ]);
        });
    }
};
