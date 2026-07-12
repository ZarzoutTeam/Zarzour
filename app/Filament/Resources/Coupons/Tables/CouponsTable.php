<?php

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->badge(),
                TextColumn::make('scope')
                    ->label('النطاق'),
                TextColumn::make('phone_number')
                    ->label('العميل')
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('النوع'),
                TextColumn::make('value')
                    ->label('القيمة')
                    ->numeric(2),
                TextColumn::make('used_count')
                    ->label('الاستخدام')
                    ->formatStateUsing(fn ($record) => $record->used_count.' / '.($record->usage_limit ?? '∞')),
                TextColumn::make('expires_at')
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
