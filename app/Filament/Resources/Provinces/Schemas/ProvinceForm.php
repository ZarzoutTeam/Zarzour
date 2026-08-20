<?php

namespace App\Filament\Resources\Provinces\Schemas;

use App\Filament\Support\UsdPricing;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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
                        TextInput::make('shipping_fee_usd')
                            ->label('أجرة الشحن بالدولار')
                            ->helperText('هذه هي الأجرة الأساسية، وتُحوّل إلى الليرة تلقائياً.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('دولار')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set) => $set('shipping_fee', UsdPricing::convertToSyp($state)))
                            ->rules([UsdPricing::exchangeRateConfiguredRule()]),
                        TextInput::make('shipping_fee')
                            ->label('أجرة الشحن المحسوبة بالليرة')
                            ->helperText(UsdPricing::sypHelperText())
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('ل.س'),
                        Toggle::make('is_active')
                            ->label('الشحن متاح لهذه المحافظة')
                            ->helperText('عند التعطيل لن يتمكن العميل من اختيار هذه المحافظة في طلب جديد.')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }
}
