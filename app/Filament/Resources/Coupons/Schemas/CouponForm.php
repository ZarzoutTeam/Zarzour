<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات القسيمة')
                    ->description('أنشئ رمز الخصم وحدد طريقة احتساب قيمته.')
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز القسيمة')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->placeholder('مثال: SUMMER25')
                            ->helperText('هذا هو الرمز الذي يدخله العميل. استخدم أحرفاً وأرقاماً وشرطات دون مسافات.')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                        Select::make('type')
                            ->label('طريقة احتساب الخصم')
                            ->options([
                                'percentage' => 'نسبة مئوية',
                                'fixed' => 'مبلغ ثابت',
                            ])
                            ->placeholder('اختر طريقة الخصم')
                            ->helperText('النسبة تُحسب من قيمة الطلب، والمبلغ الثابت يُخصم مباشرة من الإجمالي.')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== 'percentage') {
                                    $set('max_discount_amount', null);
                                }
                            }),
                        TextInput::make('value')
                            ->label('قيمة الخصم')
                            ->helperText('أدخل رقماً فقط؛ تُفسر القيمة حسب طريقة الاحتساب المحددة.')
                            ->suffix(fn (Get $get): string => $get('type') === 'percentage' ? '%' : 'ل.س')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('type') === 'percentage' ? 100.0 : null)
                            ->step(0.01),
                        TextInput::make('max_discount_amount')
                            ->label('الحد الأقصى لقيمة الخصم')
                            ->helperText('اختياري؛ مهما بلغت قيمة النسبة لن يتجاوز الخصم هذا المبلغ. اتركه فارغاً دون سقف.')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->suffix('ل.س')
                            ->visible(fn (Get $get): bool => $get('type') === 'percentage'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('المستفيد وشروط الاستخدام')
                    ->description('حدد من يستطيع استخدام القسيمة والحد الأدنى المطلوب للطلب.')
                    ->schema([
                        Select::make('scope')
                            ->label('نطاق القسيمة')
                            ->options([
                                'general' => 'عامة لجميع العملاء',
                                'user' => 'مخصصة لعميل محدد',
                            ])
                            ->default('general')
                            ->helperText('القسيمة المخصصة لا تعمل إلا مع رقم هاتف العميل المحدد.')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== 'user') {
                                    $set('phone_number', null);
                                }
                            }),
                        TextInput::make('phone_number')
                            ->label('رقم هاتف العميل')
                            ->tel()
                            ->placeholder('09XXXXXXXX')
                            ->helperText('أدخل رقم الهاتف نفسه المستخدم عند إنشاء الطلب، ويبدأ بـ 09.')
                            ->regex('/^09[0-9]{8}$/')
                            ->maxLength(10)
                            ->required(fn (Get $get) => $get('scope') === 'user')
                            ->visible(fn (Get $get) => $get('scope') === 'user'),
                        TextInput::make('min_order_amount')
                            ->label('الحد الأدنى لقيمة الطلب')
                            ->helperText('اتركه فارغاً لتعمل القسيمة مهما كانت قيمة الطلب.')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('ل.س'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('مدة القسيمة وحدودها')
                    ->description('تحكم بعدد مرات الاستخدام وتاريخ انتهاء صلاحية القسيمة.')
                    ->schema([
                        TextInput::make('usage_limit')
                            ->label('الحد الأقصى العام للاستخدام')
                            ->helperText('إجمالي مرات استخدام القسيمة من جميع العملاء. اتركه فارغاً دون حد عام.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->live(),
                        TextInput::make('per_customer_usage_limit')
                            ->label('الحد الأقصى لكل عميل')
                            ->helperText('يعتمد على رقم هاتف الطلب ويُحتسب عند إنشاء الطلب حتى لو أُلغي لاحقاً. اتركه فارغاً دون حد خاص.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(fn (Get $get): ?int => filled($get('usage_limit')) ? (int) $get('usage_limit') : null),
                        TextInput::make('used_count')
                            ->label('عدد الاستخدامات الحالية')
                            ->helperText('للقراءة فقط، ويزداد تلقائياً عند نجاح الطلب.')
                            ->disabled()
                            ->dehydrated(false)
                            ->hiddenOn('create'),
                        DateTimePicker::make('expires_at')
                            ->label('تاريخ انتهاء الصلاحية')
                            ->helperText('اتركه فارغاً لتبقى القسيمة صالحة دون تاريخ انتهاء.')
                            ->native(false),
                        Toggle::make('is_active')
                            ->label('القسيمة مفعّلة')
                            ->helperText('عند التعطيل لن يقبل المتجر هذه القسيمة.')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
