<?php

namespace App\Filament\Resources\HomepageSettings\Schemas;

use App\Models\HomepageSetting;
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
                Section::make('وسائط الواجهة الرئيسية')
                    ->description('يمكن عرض صورة رئيسية أو فيديو واحد. البانرات تبقى وحدة مستقلة.')
                    ->schema([
                        Toggle::make('hero_enabled')
                            ->label('إظهار الوسائط الرئيسية')
                            ->default(false)
                            ->live(),
                        Select::make('hero_media_type')
                            ->label('نوع الوسائط')
                            ->options([
                                'image' => 'صورة',
                                'video' => 'فيديو',
                            ])
                            ->default('image')
                            ->required()
                            ->live(),
                        SpatieMediaLibraryFileUpload::make('hero_image')
                            ->label('الصورة الرئيسية')
                            ->collection('hero_image')
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
                            ->required(fn (Get $get): bool => (bool) $get('hero_enabled') && $get('hero_media_type') === 'image')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->maxSize(config('catalog.media.max_image_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_image_mimes'))
                            ->helperText('يمكنك قص الصورة أو تدويرها وتكبيرها بعد الرفع.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('hero_video')
                            ->label('الفيديو الرئيسي')
                            ->collection('hero_video')
                            ->previewable(false)
                            ->required(fn (Get $get): bool => (bool) $get('hero_enabled') && $get('hero_media_type') === 'video')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'video')
                            ->maxSize(config('catalog.media.max_video_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_video_mimes'))
                            ->helperText('الصيغة المدعومة حاليًا MP4، والحجم الأقصى 50MB افتراضيًا.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('hero_poster')
                            ->label('صورة غلاف الفيديو')
                            ->collection('hero_poster')
                            ->conversion('hero')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '16:9',
                                '21:9',
                            ])
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'video')
                            ->maxSize(config('catalog.media.max_image_size_kb'))
                            ->acceptedFileTypes(config('catalog.media.allowed_image_mimes'))
                            ->helperText('اختيارية وتظهر قبل تشغيل الفيديو، ويمكن قصها أو تدويرها بعد الرفع.')
                            ->columnSpanFull(),
                    ]),
                Section::make('طرق الدفع')
                    ->description('الخيارات المحددة فقط ستظهر في إعدادات إتمام الطلب ويقبلها الـ API.')
                    ->schema([
                        CheckboxList::make('payment_methods')
                            ->label('طرق الدفع المفعّلة')
                            ->options(
                                collect(HomepageSetting::configuredPaymentMethods())
                                    ->mapWithKeys(fn (array $method, string $key): array => [$key => $method['label']])
                                    ->all()
                            )
                            ->default(array_keys(HomepageSetting::configuredPaymentMethods()))
                            ->columns(3)
                            ->bulkToggleable()
                            ->required(),
                    ]),
            ]);
    }
}
