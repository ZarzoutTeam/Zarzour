<?php

namespace App\Filament\Resources\Banners\Tables;

use App\Models\Banner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->reorderable('priority')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label('الصورة')
                    ->collection('image')
                    ->conversion('thumbnail'),
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('المنتج')
                    ->badge()
                    ->searchable(),
                TextColumn::make('priority')
                    ->label('ترتيب الظهور')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean(),
                TextColumn::make('visibility_status')
                    ->label('الظهور الآن')
                    ->state(fn (Banner $record): string => match (true) {
                        ! $record->hasMedia('image') => 'الصورة مفقودة',
                        ! $record->is_active => 'غير مفعّل',
                        $record->starts_at !== null && $record->starts_at->isFuture() => 'لم يبدأ',
                        $record->ends_at !== null && $record->ends_at->isPast() => 'منتهي',
                        default => 'ظاهر الآن',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ظاهر الآن' => 'success',
                        'لم يبدأ' => 'info',
                        'منتهي' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('starts_at')
                    ->label('البدء')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('الانتهاء')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
