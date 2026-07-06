<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('manager', 'web');

        $email = env('ADMIN_EMAIL', 'admin@zarzoursport.com');
        $password = env('ADMIN_PASSWORD') ?: Str::random(16);

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles(['super-admin']);

        if (! env('ADMIN_PASSWORD')) {
            $this->command->warn("تم إنشاء حساب الأدمن: {$email} — كلمة السر المولّدة عشوائياً: {$password}");
            $this->command->warn('يرجى حفظها الآن، لن تظهر مجدداً. يُفضّل ضبط ADMIN_EMAIL/ADMIN_PASSWORD في .env للبيئات الثابتة.');
        }
    }
}
