<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

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

    protected static ?string $title = 'سجل استخدام القسيمة';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الطلب'),
                TextColumn::make('customer_name')
                    ->label('اسم العميل')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->searchable(),
                TextColumn::make('coupon_discount_amount')
                    ->label('خصم القسيمة')
                    ->numeric(2)
                    ->suffix(' ل.س')
                    ->placeholder('غير متوفر للطلبات القديمة'),
                TextColumn::make('status')
                    ->label('حالة الطلب')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => OrderStatus::from($state)->getColor()),
                TextColumn::make('created_at')
                    ->label('تاريخ الاستخدام')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('عرض الطلب')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ])
            ->toolbarActions([]);
    }

    public function canCreate(): bool
    {
        return false;
    }
}
