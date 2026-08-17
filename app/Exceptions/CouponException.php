<?php

namespace App\Exceptions;

use RuntimeException;

class CouponException extends RuntimeException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(__('http.coupon_not_found'), 'coupon_not_found');
    }

    public static function expired(): self
    {
        return new self(__('http.coupon_expired'), 'coupon_expired');
    }

    public static function usageLimitReached(): self
    {
        return new self(__('http.coupon_usage_limit_reached'), 'coupon_usage_limit_reached');
    }

    public static function perCustomerUsageLimitReached(): self
    {
        return new self(__('http.coupon_customer_usage_limit_reached'), 'coupon_customer_usage_limit_reached');
    }

    public static function phoneRequired(): self
    {
        return new self(__('http.coupon_phone_required'), 'coupon_phone_required');
    }

    public static function phoneMismatch(): self
    {
        return new self(__('http.coupon_phone_mismatch'), 'coupon_phone_mismatch');
    }

    public static function belowMinimumOrderAmount(): self
    {
        return new self(__('http.coupon_below_minimum'), 'coupon_below_minimum');
    }
}
