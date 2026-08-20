<?php

namespace App\Services;

use App\Models\HomepageSetting;
use Illuminate\Support\Facades\DB;

class ExchangeRateService
{
    public function currentUsdToSypRate(): ?float
    {
        $rate = HomepageSetting::query()->value('usd_to_syp_rate');

        return $rate !== null ? (float) $rate : null;
    }

    public function convertUsdToSyp(mixed $usdPrice, ?float $rate = null): ?float
    {
        if ($usdPrice === null || $usdPrice === '') {
            return null;
        }

        $rate ??= $this->currentUsdToSypRate();

        if ($rate === null) {
            return null;
        }

        return round((float) $usdPrice * $rate, 2);
    }

    /**
     * Reprice the current catalog without firing one model event per product.
     */
    public function syncProductPrices(float $rate): int
    {
        return DB::update(
            'UPDATE products SET price_syp = ROUND(price_usd * ?, 2), updated_at = ? WHERE price_usd IS NOT NULL',
            [$rate, now()],
        );
    }

    /**
     * Reprice every current pricing definition whose source is USD. Historical
     * order and order-item amounts are intentionally excluded because they are
     * immutable SYP snapshots of the checkout that already happened.
     *
     * @return array<string, int>
     */
    public function syncAllPrices(float $rate): array
    {
        return DB::transaction(fn (): array => [
            'products' => $this->syncProductPrices($rate),
            'discounts' => DB::update(
                "UPDATE discounts SET value = ROUND(value_usd * ?, 2), updated_at = ? WHERE type = 'fixed' AND value_usd IS NOT NULL",
                [$rate, now()],
            ),
            'offers' => DB::update(
                "UPDATE offers SET discount_value = ROUND(discount_value_usd * ?, 2), updated_at = ? WHERE discount_type = 'fixed' AND discount_value_usd IS NOT NULL",
                [$rate, now()],
            ),
            'coupon_values' => DB::update(
                "UPDATE coupons SET value = ROUND(value_usd * ?, 2), updated_at = ? WHERE type = 'fixed' AND value_usd IS NOT NULL",
                [$rate, now()],
            ),
            'coupon_limits' => DB::update(
                'UPDATE coupons SET max_discount_amount = ROUND(max_discount_amount_usd * ?, 2), updated_at = ? WHERE max_discount_amount_usd IS NOT NULL',
                [$rate, now()],
            ) + DB::update(
                'UPDATE coupons SET min_order_amount = ROUND(min_order_amount_usd * ?, 2), updated_at = ? WHERE min_order_amount_usd IS NOT NULL',
                [$rate, now()],
            ),
            'provinces' => DB::update(
                'UPDATE provinces SET shipping_fee = ROUND(shipping_fee_usd * ?, 2), updated_at = ? WHERE shipping_fee_usd IS NOT NULL',
                [$rate, now()],
            ),
        ]);
    }
}
