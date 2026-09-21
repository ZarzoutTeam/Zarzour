<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المستخدم')
                    ->description('أنشئ حساباً وحدد الرول الذي يتحكم بصلاحياته داخل لوحة الإدارة.')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->helperText('يُستخدم لتسجيل الدخول إلى لوحة الإدارة.')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone_number')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->regex('/^09[0-9]{8}$/')
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            ->placeholder('09XXXXXXXX')
                            ->helperText('اختياري، وعند إدخاله يجب أن يتكون من عشرة أرقام ويبدأ بـ 09.'),
                        Select::make('roles')
                            ->label('الأدوار')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('guard_name', 'web')
                                    ->when(
                                        ! auth()->user()?->hasRole('super-admin'),
                                        fn (Builder $query): Builder => $query->where('name', '!=', 'super-admin'),
                                    ),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record): string => self::roleLabel($record->name))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->minItems(1)
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->helperText(fn (?User $record): string => $record?->is(auth()->user())
                                ? 'لا يمكن تعديل أدوار حسابك الحالي من هذه الشاشة.'
                                : 'الرول customer مخصص لعملاء التطبيق فقط؛ أي رول آخر يسمح بدخول لوحة الإدارة حسب صلاحياته.'),
                        TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->confirmed()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('مطلوبة عند الإنشاء. اتركها فارغة أثناء التعديل للاحتفاظ بكلمة المرور الحالية.'),
                        TextInput::make('password_confirmation')
                            ->label('تأكيد كلمة المرور')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation, ?string $state): bool => $operation === 'create' || filled($state))
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function roleLabel(string $role): string
    {
        return match ($role) {
            'super-admin' => 'مدير النظام (super-admin)',
            'manager' => 'مدير (manager)',
            'customer' => 'عميل (customer)',
            'panel_user' => 'مستخدم لوحة الإدارة (panel_user)',
            default => $role,
        };
    }
}
