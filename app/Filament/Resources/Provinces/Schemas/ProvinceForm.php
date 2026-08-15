<?php

namespace App\Filament\Resources\Provinces\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProvinceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('إعداد الشحن للمحافظة')
                    ->description('تظهر المحافظات المفعّلة للعميل عند إدخال عنوان الشحن.')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم المحافظة')
                            ->placeholder('مثال: دمشق')
                            ->helperText('اكتب الاسم بالطريقة التي تريد أن يظهر بها للعميل.')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('shipping_fee')
                            ->label('رسوم الشحن')
                            ->helperText('تُضاف هذه القيمة تلقائياً إلى إجمالي الطلب عند اختيار المحافظة.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('ل.س'),
                        Toggle::make('is_active')
                            ->label('الشحن متاح لهذه المحافظة')
                            ->helperText('عند التعطيل لن يتمكن العميل من اختيار هذه المحافظة في طلب جديد.')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
