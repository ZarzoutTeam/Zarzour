<?php

namespace App\Exceptions;

use RuntimeException;

class OverlappingDiscountException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
    ) {
        parent::__construct(__('http.overlapping_active_discount'));
    }
}
