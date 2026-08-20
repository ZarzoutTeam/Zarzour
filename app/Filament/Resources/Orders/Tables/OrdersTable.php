<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Actions\ChangeOrderStatusAction;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('customer_name')
                    ->label('اسم العميل')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => OrderStatus::from($state)->getColor()),
                TextColumn::make('currency')
                    ->label('عملة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'USD' ? 'دولار' : 'ليرة سورية'),
                TextColumn::make('payable_total')
                    ->label('المبلغ المطلوب تحصيله')
                    ->numeric(2)
                    ->suffix(fn (Order $record): string => $record->currency === 'USD' ? ' دولار' : ' ل.س'),
                TextColumn::make('total')
                    ->label('الإجمالي (ل.س)')
                    ->numeric(2)
                    ->suffix(' ل.س')
                    ->sortable(),
                TextColumn::make('total_usd')
                    ->label('الإجمالي ($)')
                    ->numeric(2)
                    ->suffix(' دولار')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(OrderStatus::labels()),
                Filter::make('created_at')
                    ->label('تاريخ الطلب')
                    ->schema([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ChangeOrderStatusAction::make(),
            ])
            ->toolbarActions([]);
    }
}
