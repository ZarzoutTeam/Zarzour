<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
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
                    ->label('رمز القسيمة')
                    ->searchable()
                    ->badge(),
                TextColumn::make('scope')
                    ->label('النطاق')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'user' ? 'عميل محدد' : 'جميع العملاء'),
                TextColumn::make('phone_number')
                    ->label('رقم العميل')
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('طريقة الخصم')
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'نسبة مئوية' : 'مبلغ ثابت'),
                TextColumn::make('value')
                    ->label('قيمة الخصم')
                    ->numeric(2)
                    ->suffix(fn (Coupon $record): string => $record->type === 'percentage' ? '%' : ' ل.س'),
                TextColumn::make('max_discount_amount')
                    ->label('سقف الخصم')
                    ->numeric(2)
                    ->suffix(' ل.س')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('used_count')
                    ->label('مرات الاستخدام')
                    ->formatStateUsing(fn ($record) => $record->used_count.' / '.($record->usage_limit ?? '∞')),
                TextColumn::make('per_customer_usage_limit')
                    ->label('الحد لكل عميل')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? (string) $state : '∞'),
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
