<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('محتوى اللافتة')
                    ->description('النص والمنتج اللذان سيظهران للعميل عند عرض اللافتة الإعلانية.')
                    ->schema([
                        TextInput::make('title')
                            ->label('العنوان الرئيسي')
                            ->placeholder('مثال: جهّز نفسك للتمرين')
                            ->helperText('استخدم عبارة قصيرة وواضحة تلفت انتباه العميل.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subtitle')
                            ->label('العنوان الفرعي')
                            ->placeholder('مثال: اكتشف أحدث المنتجات الرياضية')
                            ->helperText('نص اختياري يشرح العرض أو الرسالة بمزيد من الوضوح.')
                            ->maxLength(255),
                        Select::make('product_id')
                            ->label('المنتج المرتبط')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('اختر المنتج')
                            ->helperText('ينتقل العميل إلى صفحة هذا المنتج عند الضغط على اللافتة.')
                            ->required(),
                        TextInput::make('priority')
                            ->label('ترتيب الظهور')
                            ->helperText('الرقم الأصغر يظهر أولاً عند وجود أكثر من لافتة فعالة.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('مدة الظهور والحالة')
                    ->description('يمكن ترك تاريخي البدء والانتهاء فارغين لعرض اللافتة دون مدة محددة.')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('بدء الظهور')
                            ->helperText('اتركه فارغاً لتبدأ اللافتة بالظهور فور تفعيلها.')
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('انتهاء الظهور')
                            ->helperText('اتركه فارغاً ليستمر الظهور دون تاريخ انتهاء.')
                            ->native(false)
                            ->after('starts_at'),
                        Toggle::make('is_active')
                            ->label('اللافتة مفعّلة')
                            ->helperText('يجب أن تكون مفعّلة وضمن المدة المحددة حتى تظهر للعملاء.')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('صورة اللافتة')
                    ->description('استخدم صورة أفقية واضحة، وتجنب وضع نصوص مهمة قرب الحواف.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('الصورة الإعلانية')
                            ->collection('image')
                            ->conversion('hero')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '3:1',
                                '21:9',
                                '16:9',
                                '2:1',
                            ])
                            ->required()
                            ->maxSize(config('catalog.media.max_image_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_image_mimes'))
                            ->helperText('بعد الرفع يمكنك قص الصورة أو تدويرها وتكبيرها. نسبة 3:1 مناسبة غالباً للافتة العريضة.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
