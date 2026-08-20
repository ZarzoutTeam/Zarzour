<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for cart/order pricing. Applies, in this fixed order,
 * per Zarzour Sport's approved pricing rules:
 *   1. Direct product discount (discounts table)
 *   2. Coupon (general or customer-specific via phone_number), on the discounted total
 *   3. Offer (product-level discount and/or gift, checked last; gift availability
 *      is always re-checked live against current stock, never from a stored flag)
 *   4. Shipping fee (Epic 7) — a fixed per-province amount added on top of the total
 *      above, after every discount. It is never itself subject to any discount and
 *      never feeds back into steps 1-3.
 *
 * Both the read-only /api/cart/calculate preview and the actual order-creation flow
 * must call calculate() so pricing logic never diverges between the two.
 */
class PriceCalculationService
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     * @return array{
     *     currency: string,
     *     exchange_rate: array{base_currency: string, quote_currency: string, rate: float}|null,
     *     payment: array<string, mixed>,
     *     amounts: array<string, array{SYP: float|null, USD: float|null}>,
     *     subtotal: float,
     *     discount_amount: float,
     *     total_before_shipping: float,
     *     shipping_fee: float|null,
     *     shipping_required: bool,
     *     grand_total: float,
     *     is_final_total: bool,
     *     coupon: array{
     *         id: int,
     *         code: string,
     *         type: string,
     *         configured_value: float,
     *         configured_value_usd: float|null,
     *         configured_values: array{SYP: float, USD: float|null}|null,
     *         discount_amount: float,
     *         discount_amounts: array{SYP: float|null, USD: float|null},
     *         max_discount_amount: float|null,
     *         max_discount_amount_usd: float|null,
     *         min_order_amount: float|null,
     *         min_order_amount_usd: float|null,
     *         usage_limit: int|null,
     *         used_count: int,
     *         remaining_uses: int|null,
     *         per_customer_usage_limit: int|null,
     *         customer_usage_count: int|null,
     *         customer_remaining_uses: int|null
     *     }|null,
     *     lines: array<int, array{
     *         product_id: int,
     *         quantity: int,
     *         unit_price: float,
     *         original_line_total: float,
     *         direct_discount_amount: float,
     *         coupon_discount_amount: float,
     *         offer_discount_amount: float,
     *         final_line_total: float,
     *         offer_id: int|null,
     *         offer_applied: bool,
     *         gift: array{product_id: int, name: string, offer_id: int}|null,
     *         amounts: array<string, array{SYP: float|null, USD: float|null}>,
     *     }>,
     * }
     */
    public function calculate(
        array $lines,
        ?string $couponCode = null,
        ?string $phoneNumber = null,
        ?int $provinceId = null,
        string $paymentCurrency = 'SYP',
        bool $lockForUpdate = false,
    ): array
    {
        $productIds = array_column($lines, 'product_id');

        $productQuery = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with([
                'discounts' => fn ($query) => $query->activeNow()->orderByDesc('id'),
                'offers' => fn ($query) => $query->activeNow()->orderByDesc('id'),
                'offers.gifts.giftProduct',
            ])
            ->orderBy('id');

        if ($lockForUpdate) {
            $productQuery->lockForUpdate();
        }

        $products = $productQuery->get()
            ->keyBy('id');

        $rows = [];

        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);

            if ($product === null) {
                throw (new ModelNotFoundException)->setModel(Product::class, [$line['product_id']]);
            }

            $quantity = (int) $line['quantity'];
            $unitPrice = (float) $product->price_syp;
            $originalLineTotal = $this->money($unitPrice * $quantity);

            $directDiscount = $product->discounts->first();
            $directDiscountAmount = $this->applyDiscount($directDiscount?->type, $directDiscount?->value, $unitPrice, $quantity, $originalLineTotal);

            $lineAfterDirect = $this->money($originalLineTotal - $directDiscountAmount);

            $rows[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'original_line_total' => $originalLineTotal,
                'direct_discount_amount' => $directDiscountAmount,
                'line_after_direct' => $lineAfterDirect,
            ];
        }

        $subtotalAfterDirect = $this->money(array_sum(array_column($rows, 'line_after_direct')));

        $coupon = null;
        $customerUsageCount = null;
        $couponDiscountTotal = 0.0;

        if ($couponCode !== null) {
            [$coupon, $customerUsageCount] = $this->validateCoupon(
                $couponCode,
                $phoneNumber,
                $subtotalAfterDirect,
                $lockForUpdate,
            );
            $calculatedCouponDiscount = $coupon->type === 'percentage'
                ? $subtotalAfterDirect * ((float) $coupon->value / 100)
                : (float) $coupon->value;

            if ($coupon->type === 'percentage' && $coupon->max_discount_amount !== null) {
                $calculatedCouponDiscount = min($calculatedCouponDiscount, (float) $coupon->max_discount_amount);
            }

            $couponDiscountTotal = $this->money(
                min(max(0, $calculatedCouponDiscount), $subtotalAfterDirect)
            );
        }

        $couponShares = $this->allocateMoneyProportionally(
            $couponDiscountTotal,
            array_column($rows, 'line_after_direct'),
        );
        $result = [];

        foreach ($rows as $index => $row) {
            $couponShare = $couponShares[$index] ?? 0.0;

            $lineAfterCoupon = $this->money($row['line_after_direct'] - $couponShare);

            /** @var Product $product */
            $product = $row['product'];
            $offer = $product->offers->first();

            $offerDiscountAmount = 0.0;
            $offerId = null;
            $gift = null;

            if ($offer !== null) {
                $offerId = $offer->id;
                if ($offer->hasDiscount()) {
                    $offerDiscountAmount = $this->applyDiscount($offer->discount_type, $offer->discount_value, $row['unit_price'], $row['quantity'], $lineAfterCoupon);
                }

                if ($offer->hasGift()) {
                    $gift = $this->resolveGift($offer, $lockForUpdate);
                }

                if ($offer->type === 'gift_only' && $gift === null) {
                    $offerId = null;
                }
            }

            $finalLineTotal = $this->money(max(0, $lineAfterCoupon - $offerDiscountAmount));

            $result[] = [
                'product_id' => $product->id,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'original_line_total' => $row['original_line_total'],
                'direct_discount_amount' => $row['direct_discount_amount'],
                'coupon_discount_amount' => $couponShare,
                'offer_discount_amount' => $offerDiscountAmount,
                'final_line_total' => $finalLineTotal,
                'offer_id' => $offerId,
                'offer_applied' => $offerId !== null,
                'gift' => $gift,
            ];
        }

        $subtotal = $this->money(array_sum(array_column($result, 'original_line_total')));
        $total = $this->money(array_sum(array_column($result, 'final_line_total')));

        $shippingFee = null;

        if ($provinceId !== null) {
            $shippingFee = (float) Province::findOrFail($provinceId)->shipping_fee;
        }

        $discountAmount = $this->money($subtotal - $total);
        $grandTotal = $this->money($total + ($shippingFee ?? 0));
        $rate = $this->exchangeRateService->currentUsdToSypRate();

        if ($rate !== null && $rate <= 0) {
            $rate = null;
        }

        $paymentCurrency = strtoupper(trim($paymentCurrency));

        if (! in_array($paymentCurrency, ['SYP', 'USD'], true)) {
            throw ValidationException::withMessages([
                'currency' => ['العملة يجب أن تكون SYP أو USD.'],
            ]);
        }

        if ($paymentCurrency === 'USD' && $rate === null) {
            throw ValidationException::withMessages([
                'currency' => ['لا يمكن الدفع بالدولار قبل تحديد سعر الصرف من لوحة التحكم.'],
            ]);
        }

        foreach ($result as &$pricedLine) {
            $pricedLine['amounts'] = [
                'unit_price' => $this->dualAmount($pricedLine['unit_price'], $rate),
                'original_line_total' => $this->dualAmount($pricedLine['original_line_total'], $rate),
                'direct_discount_amount' => $this->dualAmount($pricedLine['direct_discount_amount'], $rate),
                'coupon_discount_amount' => $this->dualAmount($pricedLine['coupon_discount_amount'], $rate),
                'offer_discount_amount' => $this->dualAmount($pricedLine['offer_discount_amount'], $rate),
                'final_line_total' => $this->dualAmount($pricedLine['final_line_total'], $rate),
            ];
        }
        unset($pricedLine);

        return [
            'currency' => $paymentCurrency,
            'exchange_rate' => $rate !== null ? [
                'base_currency' => 'USD',
                'quote_currency' => 'SYP',
                'rate' => $rate,
            ] : null,
            'amounts' => [
                'subtotal' => $this->dualAmount($subtotal, $rate),
                'discount_amount' => $this->dualAmount($discountAmount, $rate),
                'total_before_shipping' => $this->dualAmount($total, $rate),
                'shipping_fee' => $this->dualAmount($shippingFee, $rate),
                'grand_total' => $this->dualAmount($grandTotal, $rate),
            ],
            'payment' => [
                'currency' => $paymentCurrency,
                'subtotal' => $this->paymentAmount($subtotal, $rate, $paymentCurrency),
                'discount_amount' => $this->paymentAmount($discountAmount, $rate, $paymentCurrency),
                'coupon_discount_amount' => $this->paymentAmount($couponDiscountTotal, $rate, $paymentCurrency),
                'total_before_shipping' => $this->paymentAmount($total, $rate, $paymentCurrency),
                'shipping_fee' => $this->paymentAmount($shippingFee, $rate, $paymentCurrency),
                'total' => $this->paymentAmount($grandTotal, $rate, $paymentCurrency),
            ],
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_before_shipping' => $total,
            'shipping_fee' => $shippingFee,
            'shipping_required' => $shippingFee === null,
            'grand_total' => $grandTotal,
            'is_final_total' => $shippingFee !== null,
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'configured_value' => (float) $coupon->value,
                'configured_value_usd' => $coupon->value_usd !== null
                    ? (float) $coupon->value_usd
                    : null,
                'configured_values' => $coupon->type === 'fixed' ? [
                    'SYP' => (float) $coupon->value,
                    'USD' => $coupon->value_usd !== null ? (float) $coupon->value_usd : null,
                ] : null,
                'discount_amount' => $couponDiscountTotal,
                'discount_amounts' => $this->dualAmount($couponDiscountTotal, $rate),
                'max_discount_amount' => $coupon->max_discount_amount !== null
                    ? (float) $coupon->max_discount_amount
                    : null,
                'max_discount_amount_usd' => $coupon->max_discount_amount_usd !== null
                    ? (float) $coupon->max_discount_amount_usd
                    : null,
                'min_order_amount' => $coupon->min_order_amount !== null
                    ? (float) $coupon->min_order_amount
                    : null,
                'min_order_amount_usd' => $coupon->min_order_amount_usd !== null
                    ? (float) $coupon->min_order_amount_usd
                    : null,
                'usage_limit' => $coupon->usage_limit,
                'used_count' => $coupon->used_count,
                'remaining_uses' => $coupon->usage_limit !== null
                    ? max(0, $coupon->usage_limit - $coupon->used_count)
                    : null,
                'per_customer_usage_limit' => $coupon->per_customer_usage_limit,
                'customer_usage_count' => $customerUsageCount,
                'customer_remaining_uses' => $coupon->per_customer_usage_limit !== null && $customerUsageCount !== null
                    ? max(0, $coupon->per_customer_usage_limit - $customerUsageCount)
                    : null,
            ] : null,
            'lines' => $result,
        ];
    }

    /**
     * @return array{0: Coupon, 1: int|null}
     */
    private function validateCoupon(
        string $code,
        ?string $phoneNumber,
        float $subtotalAfterDirect,
        bool $lockForUpdate,
    ): array
    {
        $couponQuery = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true);

        if ($lockForUpdate) {
            $couponQuery->lockForUpdate();
        }

        $coupon = $couponQuery->first();

        if (! $coupon) {
            throw CouponException::notFound();
        }

        if ($coupon->expires_at !== null && Carbon::parse($coupon->expires_at)->isPast()) {
            throw CouponException::expired();
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw CouponException::usageLimitReached();
        }

        if ($coupon->scope === 'user') {
            if ($phoneNumber === null) {
                throw CouponException::phoneRequired();
            }

            if ($coupon->phone_number !== $phoneNumber) {
                throw CouponException::phoneMismatch();
            }
        }

        if ($coupon->min_order_amount !== null && $subtotalAfterDirect < (float) $coupon->min_order_amount) {
            throw CouponException::belowMinimumOrderAmount();
        }

        $tracksCustomerUsage = $coupon->per_customer_usage_limit !== null || $coupon->scope === 'user';
        $customerUsageCount = null;

        if ($tracksCustomerUsage && $phoneNumber !== null) {
            $customerUsageQuery = Order::query()
                ->where('coupon_id', $coupon->id)
                ->where('phone_number', $phoneNumber);

            // A locking read is required here, not just a regular COUNT. Under
            // MySQL REPEATABLE READ it must see the order committed by the prior
            // transaction after waiting for the coupon row lock.
            $customerUsageCount = $lockForUpdate
                ? $customerUsageQuery->lockForUpdate()->get(['id'])->count()
                : $customerUsageQuery->count();
        }

        if ($coupon->per_customer_usage_limit !== null) {
            if ($customerUsageCount === null) {
                throw CouponException::phoneRequired();
            }

            if ($customerUsageCount >= $coupon->per_customer_usage_limit) {
                throw CouponException::perCustomerUsageLimitReached();
            }
        }

        return [$coupon, $customerUsageCount];
    }

    private function applyDiscount(?string $type, mixed $value, float $unitPrice, int $quantity, float $base): float
    {
        if ($type === null || $value === null) {
            return 0.0;
        }

        $amount = $type === 'percentage'
            ? $base * ((float) $value / 100)
            : (float) $value * $quantity;

        return $this->money(min(max(0, $amount), $base));
    }

    /**
     * Allocate a cart-level discount in integer cents using the largest-remainder
     * method. The returned shares always add up exactly to the requested amount,
     * and no line can receive more discount than its own value.
     *
     * @param  array<int, float>  $bases
     * @return array<int, float>
     */
    private function allocateMoneyProportionally(float $amount, array $bases): array
    {
        $amountInCents = (int) round($amount * 100);
        $baseCents = array_map(fn (float $base): int => (int) round($base * 100), $bases);
        $baseTotalInCents = array_sum($baseCents);

        if ($amountInCents <= 0 || $baseTotalInCents <= 0) {
            return array_fill(0, count($bases), 0.0);
        }

        $amountInCents = min($amountInCents, $baseTotalInCents);
        $shares = [];
        $remainders = [];

        foreach ($baseCents as $index => $baseInCents) {
            $exactShare = $amountInCents * $baseInCents / $baseTotalInCents;
            $shares[$index] = min((int) floor($exactShare), $baseInCents);
            $remainders[$index] = $exactShare - floor($exactShare);
        }

        $remainingCents = $amountInCents - array_sum($shares);
        $indices = array_keys($shares);

        usort($indices, function (int $left, int $right) use ($remainders): int {
            $remainderComparison = $remainders[$right] <=> $remainders[$left];

            return $remainderComparison !== 0 ? $remainderComparison : $left <=> $right;
        });

        foreach ($indices as $index) {
            if ($remainingCents === 0) {
                break;
            }

            if ($shares[$index] < $baseCents[$index]) {
                $shares[$index]++;
                $remainingCents--;
            }
        }

        return array_map(fn (int $share): float => $share / 100, $shares);
    }

    /**
     * Rule 4: gift availability is always re-checked live at the moment of
     * calculation (preview or order creation) against current stock, never
     * from a stored/cached flag. If unavailable, a discount-with-gift offer keeps
     * its discount, while a gift-only offer is reported as not applied.
     *
     * @return array{product_id: int, name: string, offer_id: int}|null
     */
    private function resolveGift(Offer $offer, bool $lockForUpdate): ?array
    {
        $offerGift = $offer->gifts->first();

        if ($offerGift === null) {
            return null;
        }

        $giftProductQuery = Product::query()
            ->whereKey($offerGift->gift_product_id)
            ->where('is_active', true);

        if ($lockForUpdate) {
            $giftProductQuery->lockForUpdate();
        }

        $giftProduct = $giftProductQuery->first();

        if ($giftProduct === null || $giftProduct->available_quantity < 1) {
            return null;
        }

        return [
            'product_id' => $giftProduct->id,
            'name' => $giftProduct->name,
            'offer_id' => $offer->id,
        ];
    }

    private function money(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * @return array{SYP: float|null, USD: float|null}
     */
    private function dualAmount(?float $sypAmount, ?float $rate): array
    {
        return [
            'SYP' => $sypAmount,
            'USD' => $sypAmount !== null && $rate !== null && $rate > 0
                ? $this->money($sypAmount / $rate)
                : null,
        ];
    }

    private function paymentAmount(?float $sypAmount, ?float $rate, string $currency): ?float
    {
        if ($sypAmount === null) {
            return null;
        }

        return $currency === 'USD'
            ? $this->money($sypAmount / (float) $rate)
            : $sypAmount;
    }
}
