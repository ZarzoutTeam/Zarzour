<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('إعداد العرض')
                    ->description('اختر المنتج ونوع الفائدة التي سيحصل عليها العميل.')
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج المشمول بالعرض')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('اختر المنتج')
                            ->helperText('يطبّق العرض تلقائياً عند إضافة هذا المنتج إلى الطلب.')
                            ->required(),
                        Select::make('type')
                            ->label('نوع العرض')
                            ->options([
                                'discount_only' => 'خصم فقط',
                                'discount_with_gift' => 'خصم مع هدية',
                                'gift_only' => 'هدية فقط',
                            ])
                            ->default('discount_only')
                            ->helperText('حدد ما إذا كان العميل سيحصل على خصم، أو هدية، أو كليهما.')
                            ->required()
                            ->live(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('تفاصيل الخصم والهدية')
                    ->description('تظهر الحقول المناسبة تلقائياً بحسب نوع العرض الذي اخترته.')
                    ->schema([
                        Select::make('discount_type')
                            ->label('طريقة احتساب الخصم')
                            ->options([
                                'percentage' => 'نسبة مئوية',
                                'fixed' => 'مبلغ ثابت',
                            ])
                            ->placeholder('اختر طريقة الخصم')
                            ->helperText('النسبة تُحسب من سعر المنتج، أما المبلغ الثابت فيُخصم من كل قطعة.')
                            ->required(fn (Get $get): bool => $get('type') !== 'gift_only')
                            ->visible(fn (Get $get): bool => $get('type') !== 'gift_only')
                            ->live(),
                        TextInput::make('discount_value')
                            ->label('قيمة الخصم')
                            ->helperText('أدخل رقماً فقط؛ تُفسر القيمة كنسبة أو كمبلغ حسب طريقة الاحتساب.')
                            ->suffix(fn (Get $get): string => $get('discount_type') === 'percentage' ? '%' : 'ل.س')
                            ->required(fn (Get $get): bool => $get('type') !== 'gift_only')
                            ->visible(fn (Get $get): bool => $get('type') !== 'gift_only')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('discount_type') === 'percentage' ? 100.0 : null)
                            ->step(0.01),
                        Select::make('gift_product_id')
                            ->label('المنتج المقدم كهدية')
                            ->options(fn () => Product::query()->active()->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('اختر منتج الهدية')
                            ->required(fn (Get $get): bool => in_array($get('type'), ['discount_with_gift', 'gift_only'], true))
                            ->visible(fn (Get $get): bool => in_array($get('type'), ['discount_with_gift', 'gift_only'], true))
                            ->dehydrated()
                            ->helperText('يُضاف هذا المنتج مجاناً إذا كانت كميته متوفرة لحظة إنشاء الطلب.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('مدة العرض وحالته')
                    ->description('اترك التواريخ فارغة إذا كان العرض مستمراً دون مدة محددة.')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('بدء العرض')
                            ->helperText('اتركه فارغاً ليبدأ العرض فور تفعيله.')
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('انتهاء العرض')
                            ->helperText('اتركه فارغاً ليستمر العرض دون تاريخ انتهاء.')
                            ->native(false)
                            ->after('starts_at'),
                        Toggle::make('is_active')
                            ->label('العرض مفعّل')
                            ->default(true)
                            ->required()
                            ->helperText('لا يمكن تفعيل أكثر من عرض واحد للمنتج نفسه ضمن فترات متداخلة.'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
