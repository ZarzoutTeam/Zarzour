<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Services\ExchangeRateService;
use App\Support\CatalogImageUpload;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('المعلومات الأساسية')
                    ->description('البيانات التي يراها العميل في صفحة المنتج ونتائج البحث.')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم المنتج')
                            ->placeholder('مثال: حذاء جري رياضي')
                            ->helperText('اكتب اسماً واضحاً ومختصراً كما سيظهر للعميل.')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('معرّف رابط المنتج')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('يُولّد تلقائياً من الاسم ويُستخدم داخل رابط صفحة المنتج. لا تستخدم مسافات.'),
                        Select::make('category_id')
                            ->label('فئة المنتج')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('اختر الفئة')
                            ->helperText('تحدد الفئة مكان ظهور المنتج داخل المتجر.')
                            ->required(),
                        Textarea::make('description')
                            ->label('وصف المنتج')
                            ->placeholder('اكتب المواصفات والمزايا التي تساعد العميل على اتخاذ قرار الشراء...')
                            ->helperText('يفضل ذكر المادة، المقاس، الاستخدام وأي تفاصيل مهمة للعميل.')
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('السعر والمخزون')
                    ->description('أدخل السعر بالدولار؛ يُحسب السعر السوري تلقائياً وفق سعر الصرف المحدد في إعدادات المتجر.')
                    ->schema([
                        TextInput::make('price_usd')
                            ->label('السعر بالدولار الأمريكي')
                            ->helperText('هذا هو السعر الأساسي للمنتج، ومنه يُحسب السعر بالليرة السورية.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('دولار')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $set('price_syp', app(ExchangeRateService::class)->convertUsdToSyp($state));
                            })
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (app(ExchangeRateService::class)->currentUsdToSypRate() === null) {
                                        $fail('حدد سعر صرف الدولار من لوحة التحكم الرئيسية قبل حفظ المنتج.');
                                    }
                                },
                            ]),
                        TextInput::make('price_syp')
                            ->label('السعر المحسوب بالليرة السورية')
                            ->helperText(function (): string {
                                $rate = app(ExchangeRateService::class)->currentUsdToSypRate();

                                return $rate === null
                                    ? 'لم يُحدد سعر الصرف بعد. انتقل إلى لوحة التحكم الرئيسية وحدده أولاً.'
                                    : 'سعر الصرف الحالي: '.number_format($rate, 2).' ل.س لكل دولار.';
                            })
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('ل.س'),
                        TextInput::make('stock_quantity')
                            ->label('الكمية في المخزون')
                            ->helperText('الكمية الإجمالية قبل طرح الكميات المحجوزة في الطلبات الجارية.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('الظهور في المتجر')
                    ->description('تحكم بإمكانية شراء المنتج وإبرازه في الصفحة الرئيسية.')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('المنتج متاح في المتجر')
                            ->helperText('عند التعطيل لن يظهر المنتج للعملاء ولن يكون متاحاً للشراء.')
                            ->default(true)
                            ->required(),
                        Toggle::make('is_featured')
                            ->label('إظهار كمنتج مميز')
                            ->helperText('يضيف المنتج إلى قسم المنتجات المميزة في الصفحة الرئيسية.')
                            ->default(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('الصور والفيديو')
                    ->description('الصور مرتبة حسب السحب؛ أول صورة هي الصورة الرئيسية للمنتج.')
                    ->schema([
                        CatalogImageUpload::configure(SpatieMediaLibraryFileUpload::make('images')
                            ->label('صور المنتج')
                            ->collection('images')
                            ->conversion('medium'))
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '1:1',
                                '4:5',
                                '3:4',
                                '16:9',
                            ])
                            ->multiple()
                            ->maxFiles((int) config('catalog.media.max_product_images'))
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->itemPanelAspectRatio('1:1')
                            ->openable()
                            ->downloadable()
                            ->helperText(CatalogImageUpload::limitsDescription().' اسحب المصغرات لتغيير ترتيبها، وأول صورة هي الرئيسية.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('video')
                            ->label('فيديو المنتج')
                            ->collection('video')
                            ->previewable()
                            ->openable()
                            ->downloadable()
                            ->maxSize(config('catalog.media.max_video_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_video_mimes'))
                            ->helperText('اختياري. ارفع فيديو MP4 قصيراً؛ ستظهر معاينته داخل النموذج بعد اكتمال الرفع.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
