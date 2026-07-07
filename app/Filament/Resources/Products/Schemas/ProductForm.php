<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('الرابط (Slug)')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('يُولَّد تلقائياً من الاسم، ويمكن تعديله يدوياً.'),
                Select::make('category_id')
                    ->label('الفئة')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('price')
                    ->label('السعر')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                TextInput::make('stock_quantity')
                    ->label('الكمية بالمخزون')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true)
                    ->required(),
                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(4)
                    ->columnSpanFull(),
                KeyValue::make('extra_info')
                    ->label('معلومات إضافية')
                    ->keyLabel('الحقل')
                    ->valueLabel('القيمة')
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('images')
                    ->label('الصور')
                    ->collection('images')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->panelLayout('grid')
                    ->maxSize(config('catalog.media.max_image_size_kb'))
                    ->acceptedFileTypes(config('catalog.media.allowed_image_mimes'))
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('video')
                    ->label('فيديو')
                    ->collection('video')
                    ->maxSize(config('catalog.media.max_video_size_kb'))
                    ->acceptedFileTypes(config('catalog.media.allowed_video_mimes'))
                    ->columnSpanFull(),
            ]);
    }
}
