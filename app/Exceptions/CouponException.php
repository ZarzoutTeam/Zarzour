<?php

namespace App\Exceptions;

use RuntimeException;

class CouponException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self(__('http.coupon_not_found'));
    }

    public static function expired(): self
    {
        return new self(__('http.coupon_expired'));
    }

    public static function usageLimitReached(): self
    {
        return new self(__('http.coupon_usage_limit_reached'));
    }

    public static function phoneMismatch(): self
    {
        return new self(__('http.coupon_phone_mismatch'));
    }

    public static function belowMinimumOrderAmount(): self
    {
        return new self(__('http.coupon_below_minimum'));
    }
}
