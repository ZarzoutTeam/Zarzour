<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('الكود')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->alphaDash(),
                Select::make('type')
                    ->label('نوع الخصم')
                    ->options([
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'مبلغ ثابت',
                    ])
                    ->required(),
                TextInput::make('value')
                    ->label('القيمة')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Select::make('scope')
                    ->label('النطاق')
                    ->options([
                        'general' => 'عام (لأي عميل)',
                        'user' => 'مخصص لعميل محدد',
                    ])
                    ->default('general')
                    ->required()
                    ->live(),
                TextInput::make('phone_number')
                    ->label('رقم هاتف العميل')
                    ->tel()
                    ->required(fn (Get $get) => $get('scope') === 'user')
                    ->visible(fn (Get $get) => $get('scope') === 'user'),
                TextInput::make('min_order_amount')
                    ->label('الحد الأدنى لقيمة الطلب')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                TextInput::make('usage_limit')
                    ->label('الحد الأقصى لعدد الاستخدامات')
                    ->helperText('اتركه فارغاً لعدد استخدامات غير محدود.')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('used_count')
                    ->label('عدد مرات الاستخدام الحالي')
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create'),
                DateTimePicker::make('expires_at')
                    ->label('تاريخ الانتهاء')
                    ->native(false),
                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true)
                    ->required(),
            ]);
    }
}
