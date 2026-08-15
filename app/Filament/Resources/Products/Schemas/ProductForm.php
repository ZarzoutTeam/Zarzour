<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
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
                    ->description('حدد سعري البيع والكمية الفعلية المتاحة للطلبات.')
                    ->schema([
                        TextInput::make('price_syp')
                            ->label('السعر بالليرة السورية')
                            ->helperText('السعر المعروض عندما يختار العميل الليرة السورية.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('ل.س'),
                        TextInput::make('price_usd')
                            ->label('السعر بالدولار الأمريكي')
                            ->helperText('السعر المعروض عندما يختار العميل الدولار الأمريكي.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('دولار'),
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
                Section::make('المواصفات الإضافية')
                    ->description('أضف مواصفات مرنة مثل اللون أو الخامة أو بلد المنشأ.')
                    ->schema([
                        KeyValue::make('extra_info')
                            ->label('المواصفات')
                            ->keyLabel('اسم الخاصية')
                            ->valueLabel('القيمة')
                            ->helperText('مثال: اسم الخاصية «اللون»، والقيمة «أسود».')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('الصور والفيديو')
                    ->description('الصور مرتبة حسب السحب؛ أول صورة هي الصورة الرئيسية للمنتج.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->label('صور المنتج')
                            ->collection('images')
                            ->conversion('medium')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '1:1',
                                '4:5',
                                '3:4',
                                '16:9',
                            ])
                            ->multiple()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->maxSize(config('catalog.media.max_image_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_image_mimes'))
                            ->helperText('يمكنك قص كل صورة وتدويرها وتكبيرها بعد رفعها، ثم سحب الصور لتغيير ترتيبها.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('video')
                            ->label('فيديو المنتج')
                            ->collection('video')
                            ->previewable(false)
                            ->maxSize(config('catalog.media.max_video_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_video_mimes'))
                            ->helperText('اختياري. ارفع فيديو قصيراً بصيغة مدعومة لعرض المنتج بشكل أوضح.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
