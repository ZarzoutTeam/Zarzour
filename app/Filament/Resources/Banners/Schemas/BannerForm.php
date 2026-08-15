<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label('العنوان الفرعي')
                    ->maxLength(255),
                Select::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('priority')
                    ->label('الأولوية')
                    ->helperText('الرقم الأصغر يظهر أولاً.')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true)
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->label('تاريخ البدء')
                    ->native(false),
                DateTimePicker::make('ends_at')
                    ->label('تاريخ الانتهاء')
                    ->native(false)
                    ->after('starts_at'),
                SpatieMediaLibraryFileUpload::make('image')
                    ->label('الصورة')
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
                    ->helperText('بعد الرفع يمكنك قص الصورة أو تدويرها وتكبيرها. نسبة 3:1 مناسبة غالباً للبانر العريض.')
                    ->columnSpanFull(),
            ]);
    }
}
