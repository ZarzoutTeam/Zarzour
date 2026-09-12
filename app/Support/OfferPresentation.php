<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\Product;

final class OfferPresentation
{
    /**
     * Build the public, backward-compatible offer contract used by product lists
     * and product details. Custom campaign art takes precedence over product art.
     *
     * @return array<string, mixed>
     */
    public static function payload(Offer $offer, Product $product): array
    {
        $giftProduct = $offer->gifts->first()?->giftProduct;
        $offerImage = $offer->getFirstMedia(Offer::MEDIA_OFFER_IMAGE) ?? $product->primaryImage;
        $giftImage = $offer->getFirstMedia(Offer::MEDIA_GIFT_IMAGE) ?? $giftProduct?->primaryImage;

        return [
            'id' => $offer->id,
            'type' => $offer->type,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value !== null ? (float) $offer->discount_value : null,
            'discount_value_usd' => $offer->discount_type === 'fixed' && $offer->discount_value_usd !== null
                ? (float) $offer->discount_value_usd
                : null,
            'discount_values' => $offer->discount_type === 'fixed' ? [
                'SYP' => (float) $offer->discount_value,
                'USD' => $offer->discount_value_usd !== null ? (float) $offer->discount_value_usd : null,
            ] : null,
            'image' => CatalogMedia::imagePayload($offerImage),
            'gift' => $giftProduct ? [
                'product_id' => $giftProduct->id,
                'name' => $giftProduct->name,
                'available' => $giftProduct->is_active && $giftProduct->available_quantity > 0,
                'image' => CatalogMedia::imagePayload($giftImage),
            ] : null,
        ];
    }
}
