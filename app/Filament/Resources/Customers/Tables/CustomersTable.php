<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount('orders')
                ->withSum('confirmedOrders', 'total')
                ->withMax('orders', 'created_at'))
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label('الحالة')
                    ->badge()
                    // TextColumn skips formatStateUsing()/badge() entirely when the raw
                    // state is null (guest customers), so the state is resolved to a
                    // non-null string here instead of relying on the raw user_id value.
                    ->state(fn (Customer $record): string => $record->user_id !== null ? 'registered' : 'guest')
                    ->formatStateUsing(fn (string $state): string => $state === 'registered' ? 'مسجّل' : 'زائر')
                    ->color(fn (string $state): string => $state === 'registered' ? 'success' : 'gray'),
                TextColumn::make('orders_count')
                    ->label('عدد الطلبات')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('confirmed_orders_sum_total')
                    ->label('إجمالي المصروف')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('orders_max_created_at')
                    ->label('تاريخ آخر طلب')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('user_id')
                    ->label('الحالة')
                    ->nullable()
                    ->trueLabel('مسجّل')
                    ->falseLabel('زائر')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_at')
                    ->label('تاريخ التسجيل')
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
            ])
            ->toolbarActions([]);
    }
}
