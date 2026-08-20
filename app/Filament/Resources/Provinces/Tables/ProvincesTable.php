<?php

namespace App\Filament\Resources\Provinces\Tables;

use App\Filament\Support\UsdPricing;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProvincesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('اسم المحافظة')
                    ->searchable(),
                TextInputColumn::make('shipping_fee_usd')
                    ->label('أجرة الشحن ($)')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0', UsdPricing::exchangeRateConfiguredRule()]),
                TextColumn::make('shipping_fee')
                    ->label('المحسوب (ل.س)')
                    ->numeric(2),
                IconColumn::make('is_active')
                    ->label('مفعّلة')
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
