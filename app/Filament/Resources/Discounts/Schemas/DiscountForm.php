<?php

namespace App\Filament\Resources\Discounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->label('نوع الخصم')
                    ->options([
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'مبلغ ثابت',
                    ])
                    ->required(),
                TextInput::make('value')
                    ->label('القيمة')
                    ->helperText('عند اختيار مبلغ ثابت تكون القيمة بالليرة السورية.')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                DateTimePicker::make('starts_at')
                    ->label('تاريخ البدء')
                    ->native(false),
                DateTimePicker::make('ends_at')
                    ->label('تاريخ الانتهاء')
                    ->native(false)
                    ->after('starts_at'),
                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true)
                    ->required(),
            ]);
    }
}
