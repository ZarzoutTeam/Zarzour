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
}
