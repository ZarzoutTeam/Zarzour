<?php

namespace App\Exceptions;

use RuntimeException;

class OutOfStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requestedQuantity,
    ) {
        parent::__construct(__('http.out_of_stock'));
    }
}
