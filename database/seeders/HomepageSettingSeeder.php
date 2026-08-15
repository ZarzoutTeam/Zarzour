<?php

namespace Database\Seeders;

use App\Models\HomepageSetting;
use Illuminate\Database\Seeder;

class HomepageSettingSeeder extends Seeder
{
    public function run(): void
    {
        HomepageSetting::query()->firstOrCreate(
            ['key' => 'default'],
            [
                'hero_media_type' => 'image',
                'hero_enabled' => false,
                'payment_methods' => array_keys(HomepageSetting::configuredPaymentMethods()),
            ],
        );
    }
}
