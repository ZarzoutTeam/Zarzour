<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Support\CatalogImageUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                Section::make('وسائط اللافتة')
                    ->description('اختر صورة أفقية أو فيديو قصيراً ليظهر داخل اللافتة الإعلانية.')
                    ->schema([
                        Select::make('media_type')
                            ->label('نوع الوسائط')
                            ->options([
                                'image' => 'صورة',
                                'video' => 'فيديو',
                            ])
                            ->default('image')
                            ->required()
                            ->live()
                            ->helperText('عند تغيير النوع وحفظ اللافتة، تُحذف وسائط النوع السابق.'),
                        CatalogImageUpload::configure(SpatieMediaLibraryFileUpload::make('image')
                            ->label('الصورة الإعلانية')
                            ->collection('image')
                            ->conversion('hero'))
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '3:1',
                                '21:9',
                                '16:9',
                                '2:1',
                            ])
                            ->required(fn (Get $get): bool => $get('media_type') === 'image')
                            ->visible(fn (Get $get): bool => $get('media_type') === 'image')
                            ->helperText(CatalogImageUpload::limitsDescription().' تُعرض نسخة WebP عالية الدقة، ونسبة 3:1 مناسبة غالباً.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('video')
                            ->label('فيديو اللافتة')
                            ->collection('video')
                            ->previewable()
                            ->openable()
                            ->downloadable()
                            ->required(fn (Get $get): bool => $get('media_type') === 'video')
                            ->visible(fn (Get $get): bool => $get('media_type') === 'video')
                            ->maxSize(config('catalog.media.max_video_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_video_mimes'))
                            ->helperText('الصيغة المدعومة حالياً MP4، والحجم الأقصى الافتراضي 50 ميغابايت.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
