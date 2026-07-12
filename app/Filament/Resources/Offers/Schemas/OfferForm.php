<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OfferForm
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
                    ->label('نوع العرض')
                    ->options([
                        'discount_only' => 'خصم فقط',
                        'discount_with_gift' => 'خصم + هدية',
                    ])
                    ->default('discount_only')
                    ->required()
                    ->live(),
                Select::make('discount_type')
                    ->label('نوع الخصم')
                    ->options([
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'مبلغ ثابت',
                    ])
                    ->required(),
                TextInput::make('discount_value')
                    ->label('قيمة الخصم')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Select::make('gift_product_id')
                    ->label('منتج الهدية')
                    ->options(fn () => Product::query()->active()->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn (Get $get) => $get('type') === 'discount_with_gift')
                    ->visible(fn (Get $get) => $get('type') === 'discount_with_gift')
                    ->dehydrated()
                    ->helperText('المنتج الذي سيُمنح مجاناً عند توفر مخزونه لحظة الطلب.'),
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
                    ->required()
                    ->helperText('لا يمكن تفعيل أكثر من عرض واحد لنفس المنتج بفترات زمنية متداخلة.'),
            ]);
    }
}
