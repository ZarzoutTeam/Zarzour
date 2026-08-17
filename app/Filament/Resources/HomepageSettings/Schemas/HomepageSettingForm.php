<?php

namespace App\Filament\Resources\HomepageSettings\Schemas;

use App\Models\HomepageSetting;
use App\Support\CatalogImageUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class HomepageSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('الغلاف الرئيسي للتطبيق')
                    ->description('هذه الوسائط تظهر أعلى الصفحة الرئيسية. أما اللافتات الإعلانية فتُدار من قسم مستقل.')
                    ->schema([
                        Toggle::make('hero_enabled')
                            ->label('إظهار الغلاف الرئيسي')
                            ->helperText('عند التعطيل لن تظهر الصورة أو الفيديو في أعلى الصفحة الرئيسية.')
                            ->default(false)
                            ->live(),
                        Select::make('hero_media_type')
                            ->label('نوع الغلاف')
                            ->options([
                                'image' => 'صورة',
                                'video' => 'فيديو',
                            ])
                            ->default('image')
                            ->helperText('اختر الوسيط الذي تريد عرضه للعميل في أعلى الصفحة.')
                            ->required()
                            ->live(),
                        CatalogImageUpload::configure(SpatieMediaLibraryFileUpload::make('hero_image')
                            ->label('صورة الغلاف الرئيسية')
                            ->collection('hero_image')
                            ->conversion('hero'))
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '3:1',
                                '21:9',
                                '16:9',
                                '2:1',
                            ])
                            ->required(fn (Get $get): bool => (bool) $get('hero_enabled') && $get('hero_media_type') === 'image')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->helperText(CatalogImageUpload::limitsDescription().' استخدم صورة أفقية واضحة؛ نسبة 3:1 مناسبة غالباً.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('hero_video')
                            ->label('فيديو الغلاف الرئيسي')
                            ->collection('hero_video')
                            ->previewable()
                            ->openable()
                            ->downloadable()
                            ->required(fn (Get $get): bool => (bool) $get('hero_enabled') && $get('hero_media_type') === 'video')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'video')
                            ->maxSize(config('catalog.media.max_video_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_video_mimes'))
                            ->helperText('الصيغة المدعومة حالياً إم بي 4 (MP4)، والحجم الأقصى الافتراضي 50 ميغابايت.')
                            ->columnSpanFull(),
                        CatalogImageUpload::configure(SpatieMediaLibraryFileUpload::make('hero_poster')
                            ->label('صورة غلاف الفيديو')
                            ->collection('hero_poster')
                            ->conversion('hero'))
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '16:9',
                                '21:9',
                            ])
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'video')
                            ->helperText('اختيارية، وتظهر قبل تشغيل الفيديو. '.CatalogImageUpload::limitsDescription())
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('طرق الدفع المتاحة')
                    ->description('الخيارات المحددة فقط ستظهر للعميل عند إتمام الطلب وسيقبلها التطبيق.')
                    ->schema([
                        CheckboxList::make('payment_methods')
                            ->label('اختر طرق الدفع')
                            ->options(
                                collect(HomepageSetting::configuredPaymentMethods())
                                    ->mapWithKeys(fn (array $method, string $key): array => [$key => $method['label']])
                                    ->all()
                            )
                            ->default(array_keys(HomepageSetting::configuredPaymentMethods()))
                            ->columns(3)
                            ->bulkToggleable()
                            ->helperText('يجب إبقاء طريقة دفع واحدة على الأقل مفعّلة حتى يستطيع العميل إتمام الطلب.')
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
