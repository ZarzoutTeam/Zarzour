<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'stock_reservation_timeout_hours', 'value' => '24', 'type' => 'integer'],
            ['key' => 'store_name', 'value' => 'Zarzour Sport', 'type' => 'string'],
            ['key' => 'store_phone', 'value' => '+963900000000', 'type' => 'string'],
            ['key' => 'store_email', 'value' => 'contact@zarzoursport.com', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
