<?php

namespace App\Filament\Resources\Discounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('تفاصيل الخصم')
                    ->description('حدد المنتج وطريقة احتساب التخفيض على سعره.')
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('اختر المنتج')
                            ->helperText('يطبّق الخصم على هذا المنتج فقط.')
                            ->required(),
                        Select::make('type')
                            ->label('طريقة احتساب الخصم')
                            ->options([
                                'percentage' => 'نسبة مئوية',
                                'fixed' => 'مبلغ ثابت',
                            ])
                            ->placeholder('اختر طريقة الخصم')
                            ->helperText('النسبة تُحسب من سعر المنتج، والمبلغ الثابت يُخصم مباشرة من السعر.')
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->label('قيمة الخصم')
                            ->helperText('أدخل رقماً فقط؛ تُفسر القيمة حسب طريقة الاحتساب المحددة.')
                            ->suffix(fn (Get $get): string => $get('type') === 'percentage' ? '%' : 'ل.س')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('type') === 'percentage' ? 100.0 : null)
                            ->step(0.01),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('مدة الخصم وحالته')
                    ->description('يمكن ترك التواريخ فارغة ليبقى الخصم فعالاً دون مدة محددة.')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('بدء الخصم')
                            ->helperText('اتركه فارغاً ليبدأ الخصم فور تفعيله.')
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('انتهاء الخصم')
                            ->helperText('اتركه فارغاً ليستمر الخصم دون تاريخ انتهاء.')
                            ->native(false)
                            ->after('starts_at'),
                        Toggle::make('is_active')
                            ->label('الخصم مفعّل')
                            ->helperText('عند التعطيل لن يُحتسب الخصم حتى لو كان ضمن المدة المحددة.')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
