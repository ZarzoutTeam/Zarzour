<?php

namespace App\Filament\Resources\Discounts\Schemas;

use App\Filament\Support\UsdPricing;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === 'percentage') {
                                    $set('value_usd', null);
                                } else {
                                    $set('value', null);
                                }
                            }),
                        TextInput::make('value_usd')
                            ->label('قيمة الخصم بالدولار')
                            ->helperText('هذا هو المبلغ الأساسي للخصم الثابت، ويُحوّل إلى الليرة تلقائياً.')
                            ->suffix('دولار')
                            ->visible(fn (Get $get): bool => $get('type') === 'fixed')
                            ->required(fn (Get $get): bool => $get('type') === 'fixed')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set) => $set('value', UsdPricing::convertToSyp($state)))
                            ->rules([UsdPricing::exchangeRateConfiguredRule()]),
                        TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === 'fixed' ? 'الخصم المحسوب بالليرة' : 'نسبة الخصم')
                            ->helperText(fn (Get $get): string => $get('type') === 'fixed' ? UsdPricing::sypHelperText() : 'أدخل نسبة الخصم من سعر المنتج.')
                            ->suffix(fn (Get $get): string => $get('type') === 'percentage' ? '%' : 'ل.س')
                            ->required(fn (Get $get): bool => $get('type') === 'percentage')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('type') === 'percentage' ? 100.0 : null)
                            ->step(0.01)
                            ->disabled(fn (Get $get): bool => $get('type') === 'fixed')
                            ->dehydrated(fn (Get $get): bool => $get('type') === 'percentage'),
                    ])
                    ->columns(4)
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
