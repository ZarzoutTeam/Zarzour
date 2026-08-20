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
            $table->decimal('value_usd', 10, 2)->nullable()->after('value');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->decimal('discount_value_usd', 10, 2)->nullable()->after('discount_value');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('value_usd', 10, 2)->nullable()->after('value');
            $table->decimal('max_discount_amount_usd', 10, 2)->nullable()->after('max_discount_amount');
            $table->decimal('min_order_amount_usd', 10, 2)->nullable()->after('min_order_amount');
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->decimal('shipping_fee', 14, 2)->change();
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->decimal('shipping_fee_usd', 10, 2)->nullable()->after('shipping_fee');
        });

        $rate = (float) (DB::table('homepage_settings')
            ->whereNotNull('usd_to_syp_rate')
            ->value('usd_to_syp_rate') ?? 0);

        if ($rate > 0) {
            $this->backfillUsdColumn('discounts', 'value', 'value_usd', $rate, ['type' => 'fixed']);
            $this->backfillUsdColumn('offers', 'discount_value', 'discount_value_usd', $rate, ['discount_type' => 'fixed']);
            $this->backfillUsdColumn('coupons', 'value', 'value_usd', $rate, ['type' => 'fixed']);
            $this->backfillUsdColumn('coupons', 'max_discount_amount', 'max_discount_amount_usd', $rate);
            $this->backfillUsdColumn('coupons', 'min_order_amount', 'min_order_amount_usd', $rate);
            $this->backfillUsdColumn('provinces', 'shipping_fee', 'shipping_fee_usd', $rate);
        }
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropColumn('shipping_fee_usd');
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->decimal('shipping_fee', 10, 2)->change();
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['value_usd', 'max_discount_amount_usd', 'min_order_amount_usd']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('discount_value_usd');
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn('value_usd');
        });
    }

    /**
     * Preserve existing SYP definitions by deriving their USD source from the
     * exchange rate that was active when this migration ran.
     *
     * @param  array<string, mixed>  $conditions
     */
    private function backfillUsdColumn(
        string $table,
        string $sypColumn,
        string $usdColumn,
        float $rate,
        array $conditions = [],
    ): void {
        $query = DB::table($table)->whereNotNull($sypColumn);

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($query->get(['id', $sypColumn]) as $record) {
            DB::table($table)
                ->where('id', $record->id)
                ->update([$usdColumn => round((float) $record->{$sypColumn} / $rate, 2)]);
        }
    }
};
