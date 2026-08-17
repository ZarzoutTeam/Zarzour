<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('value', 14, 2)->change();
            $table->decimal('min_order_amount', 14, 2)->nullable()->change();
            $table->decimal('max_discount_amount', 14, 2)->nullable()->after('value');
            $table->unsignedInteger('per_customer_usage_limit')->nullable()->after('usage_limit');
        });

        DB::table('coupons')->update(['code' => DB::raw('UPPER(TRIM(code))')]);

        // Align legacy counters with the actual coupon-bearing orders before the
        // new global and per-customer limits start enforcing them.
        foreach (DB::table('coupons')->pluck('id') as $couponId) {
            DB::table('coupons')
                ->where('id', $couponId)
                ->update([
                    'used_count' => DB::table('orders')->where('coupon_id', $couponId)->count(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('per_customer_usage_limit');
            $table->dropColumn('max_discount_amount');
            $table->decimal('value', 10, 2)->change();
            $table->decimal('min_order_amount', 10, 2)->nullable()->change();
        });
    }
};
