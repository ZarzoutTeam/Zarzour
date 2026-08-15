<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'الملف الشخصي';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent()
                    ->label('الاسم الكامل')
                    ->helperText('يظهر هذا الاسم في قائمة الحساب أعلى لوحة التحكم.'),
                $this->getEmailFormComponent()
                    ->label('البريد الإلكتروني')
                    ->helperText('يُستخدم لتسجيل الدخول إلى لوحة التحكم.'),
                TextInput::make('phone_number')
                    ->label('رقم الهاتف')
                    ->tel()
                    ->regex('/^09[0-9]{8}$/')
                    ->unique(ignoreRecord: true)
                    ->maxLength(10)
                    ->placeholder('09XXXXXXXX')
                    ->helperText('اختياري، وعند إدخاله يجب أن يتكون من عشرة أرقام ويبدأ بـ 09.'),
                $this->getPasswordFormComponent()
                    ->label('كلمة المرور الجديدة')
                    ->helperText('اتركها فارغة إذا لم ترد تغيير كلمة المرور.'),
                $this->getPasswordConfirmationFormComponent()
                    ->label('تأكيد كلمة المرور الجديدة')
                    ->helperText('أعد كتابة كلمة المرور الجديدة للتأكد من صحتها.'),
                $this->getCurrentPasswordFormComponent()
                    ->label('كلمة المرور الحالية')
                    ->helperText('مطلوبة فقط عند تغيير كلمة المرور.'),
            ]);
    }
}
