<?php

namespace App\Rules;

use App\Models\Province;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Distinguishes "province doesn't exist" from "province exists but is disabled"
 * so /cart/calculate and /orders return a specific Arabic message for each case
 * instead of a generic "invalid" error.
 */
class ActiveProvinceExists implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $province = Province::find($value);

        if ($province === null) {
            $fail(__('http.province_not_found'));

            return;
        }

        if (! $province->is_active) {
            $fail(__('http.province_not_active'));
        }
    }
}
