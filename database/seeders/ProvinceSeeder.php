<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * أجور الشحن أدناه قيم placeholder مؤقتة (وليست نهائية) بانتظار تأكيد العميل
     * للتسعير الفعلي لكل محافظة. رُتّبت تقريبياً حسب البعد عن دمشق (15000-25000 ل.س)
     * فقط لتعطي شكلاً واقعياً للوحة التحكم أثناء التطوير والاختبار.
     */
    public function run(): void
    {
        $provinces = [
            'دمشق' => 15000,
            'ريف دمشق' => 17000,
            'القنيطرة' => 18000,
            'درعا' => 18000,
            'السويداء' => 19000,
            'حمص' => 19000,
            'حماة' => 20000,
            'طرطوس' => 21000,
            'اللاذقية' => 21000,
            'إدلب' => 22000,
            'حلب' => 22000,
            'دير الزور' => 24000,
            'الرقة' => 24000,
            'الحسكة' => 25000,
        ];

        foreach ($provinces as $name => $shippingFee) {
            Province::updateOrCreate(
                ['name' => $name],
                ['shipping_fee' => $shippingFee, 'is_active' => true],
            );
        }
    }
}
