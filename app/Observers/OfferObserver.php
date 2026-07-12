<?php

namespace App\Observers;

use App\Exceptions\OverlappingOfferException;
use App\Models\Offer;
use Illuminate\Support\Facades\Cache;

class OfferObserver
{
    public function saving(Offer $offer): void
    {
        if (! $offer->is_active) {
            return;
        }

        if (Offer::overlapsExistingActive($offer->product_id, $offer->starts_at, $offer->ends_at, $offer->id)) {
            throw new OverlappingOfferException($offer->product_id);
        }
    }

    public function saved(Offer $offer): void
    {
        Cache::forget('home.public.snapshot');
    }

    public function deleted(Offer $offer): void
    {
        Cache::forget('home.public.snapshot');
    }
}
