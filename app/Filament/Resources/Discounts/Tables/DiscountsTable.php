<?php

namespace App\Filament\Resources\Discounts\Tables;

use App\Models\Discount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('طريقة الخصم')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'نسبة مئوية' : 'مبلغ ثابت'),
                TextColumn::make('value')
                    ->label('النسبة / القيمة بالليرة')
                    ->numeric(2)
                    ->suffix(fn (Discount $record): string => $record->type === 'percentage' ? '%' : ' ل.س'),
                TextColumn::make('value_usd')
                    ->label('القيمة الأساسية ($)')
                    ->numeric(2)
                    ->suffix(' دولار')
                    ->placeholder('—'),
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
                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean(),
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
