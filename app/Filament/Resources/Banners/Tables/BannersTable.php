<?php

namespace App\Filament\Resources\Banners\Tables;

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
                    ->label('الأولوية')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean(),
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
