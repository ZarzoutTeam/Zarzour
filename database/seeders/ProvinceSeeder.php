<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * أجور الشحن أدناه قيم placeholder مؤقتة بانتظار تأكيد العميل للتسعير الفعلي لكل محافظة.
     */
    public function run(): void
    {
        $provinces = [
            'دمشق',
            'ريف دمشق',
            'حلب',
            'حمص',
            'حماة',
            'اللاذقية',
            'طرطوس',
            'إدلب',
            'درعا',
            'السويداء',
            'القنيطرة',
            'دير الزور',
            'الرقة',
            'الحسكة',
        ];

        foreach ($provinces as $name) {
            Province::updateOrCreate(
                ['name' => $name],
                ['shipping_fee' => 5000],
            );
        }
    }
}
