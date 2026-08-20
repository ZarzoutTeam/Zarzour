<?php

namespace App\Rules;

use App\Services\ExchangeRateService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExchangeRateConfigured implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (app(ExchangeRateService::class)->currentUsdToSypRate() === null) {
            $fail('حدد سعر صرف الدولار من لوحة التحكم الرئيسية قبل الحفظ.');
        }
    }
}
