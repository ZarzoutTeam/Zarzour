<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Province;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for cart/order pricing. Applies, in this fixed order,
 * per Zarzour Sport's approved pricing rules:
 *   1. Direct product discount (discounts table)
 *   2. Coupon (general or customer-specific via phone_number), on the discounted total
 *   3. Offer (product-level discount + optional gift, checked last; gift availability
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
    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     * @return array{
     *     subtotal: float,
     *     discount_amount: float,
     *     total_before_shipping: float,
     *     shipping_fee: float|null,
     *     shipping_required: bool,
     *     grand_total: float,
     *     is_final_total: bool,
     *     coupon: array{id: int, code: string, discount_amount: float}|null,
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
     *         gift: array{product_id: int, name: string, offer_id: int}|null,
     *     }>,
     * }
     */
    public function calculate(array $lines, ?string $couponCode = null, ?string $phoneNumber = null, ?int $provinceId = null): array
    {
        $productIds = array_column($lines, 'product_id');

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with([
                'discounts' => fn ($query) => $query->activeNow(),
                'offers' => fn ($query) => $query->activeNow(),
                'offers.gifts.giftProduct',
            ])
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);

            if ($product === null) {
                throw (new ModelNotFoundException)->setModel(Product::class, [$line['product_id']]);
            }

            $quantity = (int) $line['quantity'];
            $unitPrice = (float) $product->price;
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
        $couponDiscountTotal = 0.0;

        if ($couponCode !== null) {
            $coupon = $this->validateCoupon($couponCode, $phoneNumber, $subtotalAfterDirect);
            $couponDiscountTotal = $this->money(
                $coupon->type === 'percentage'
                    ? $subtotalAfterDirect * ((float) $coupon->value / 100)
                    : min((float) $coupon->value, $subtotalAfterDirect)
            );
        }

        $couponRemaining = $couponDiscountTotal;
        $lineCount = count($rows);
        $result = [];

        foreach ($rows as $index => $row) {
            $isLastLine = $index === $lineCount - 1;

            if ($couponDiscountTotal > 0 && $subtotalAfterDirect > 0) {
                $couponShare = $isLastLine
                    ? $couponRemaining
                    : $this->money($row['line_after_direct'] / $subtotalAfterDirect * $couponDiscountTotal);
                $couponRemaining = $this->money($couponRemaining - $couponShare);
            } else {
                $couponShare = 0.0;
            }

            $lineAfterCoupon = $this->money($row['line_after_direct'] - $couponShare);

            /** @var Product $product */
            $product = $row['product'];
            $offer = $product->offers->first();

            $offerDiscountAmount = 0.0;
            $offerId = null;
            $gift = null;

            if ($offer !== null) {
                $offerId = $offer->id;
                $offerDiscountAmount = $this->applyDiscount($offer->discount_type, $offer->discount_value, $row['unit_price'], $row['quantity'], $lineAfterCoupon);

                if ($offer->type === 'discount_with_gift') {
                    $gift = $this->resolveGift($offer);
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
                'gift' => $gift,
            ];
        }

        $subtotal = $this->money(array_sum(array_column($result, 'original_line_total')));
        $total = $this->money(array_sum(array_column($result, 'final_line_total')));

        $shippingFee = null;

        if ($provinceId !== null) {
            $shippingFee = (float) Province::findOrFail($provinceId)->shipping_fee;
        }

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $this->money($subtotal - $total),
            'total_before_shipping' => $total,
            'shipping_fee' => $shippingFee,
            'shipping_required' => $shippingFee === null,
            'grand_total' => $this->money($total + ($shippingFee ?? 0)),
            'is_final_total' => $shippingFee !== null,
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_amount' => $couponDiscountTotal,
            ] : null,
            'lines' => $result,
        ];
    }

    private function validateCoupon(string $code, ?string $phoneNumber, float $subtotalAfterDirect): Coupon
    {
        $coupon = Coupon::query()->where('code', $code)->where('is_active', true)->first();

        if (! $coupon) {
            throw CouponException::notFound();
        }

        if ($coupon->expires_at !== null && Carbon::parse($coupon->expires_at)->isPast()) {
            throw CouponException::expired();
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw CouponException::usageLimitReached();
        }

        if ($coupon->phone_number !== null && $coupon->phone_number !== $phoneNumber) {
            throw CouponException::phoneMismatch();
        }

        if ($coupon->min_order_amount !== null && $subtotalAfterDirect < (float) $coupon->min_order_amount) {
            throw CouponException::belowMinimumOrderAmount();
        }

        return $coupon;
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
     * Rule 4: gift availability is always re-checked live at the moment of
     * calculation (preview or order creation) against current stock, never
     * from a stored/cached flag. If unavailable, the offer silently degrades
     * to discount-only — no gift, no error.
     *
     * @return array{product_id: int, name: string, offer_id: int}|null
     */
    private function resolveGift(Offer $offer): ?array
    {
        $offerGift = $offer->gifts->first();

        if ($offerGift === null) {
            return null;
        }

        $giftProduct = Product::query()->find($offerGift->gift_product_id);

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
}
