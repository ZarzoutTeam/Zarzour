<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'الطلبات';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الطلب'),
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => OrderStatus::from($state)->getColor()),
                TextColumn::make('total')
                    ->label('الإجمالي الكلي')
                    ->numeric(2)
                    ->suffix(fn (Order $record): string => $record->currency === 'USD' ? ' دولار' : ' ل.س'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('عرض')
                    ->url(fn ($record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ])
            ->toolbarActions([]);
    }

    public function canCreate(): bool
    {
        return false;
    }
}
