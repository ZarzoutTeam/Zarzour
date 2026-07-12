<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidOrderStatusTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(__('http.invalid_order_status_transition', ['from' => $from, 'to' => $to]));
    }
}
