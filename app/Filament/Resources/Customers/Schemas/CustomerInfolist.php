<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات العميل')
                    ->description('معلومات الحساب والتواصل المسجلة لهذا العميل.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('name')->label('الاسم'),
                            TextEntry::make('phone_number')->label('رقم الهاتف'),
                            TextEntry::make('user_id')
                                ->label('الحالة')
                                ->badge()
                                // formatStateUsing()/badge() are skipped on a null raw state
                                // (guest customers), so the state is resolved explicitly here.
                                ->state(fn (Customer $record): string => $record->user_id !== null ? 'registered' : 'guest')
                                ->formatStateUsing(fn (string $state): string => $state === 'registered' ? 'مسجّل' : 'زائر')
                                ->color(fn (string $state): string => $state === 'registered' ? 'success' : 'gray'),
                            TextEntry::make('user.email')->label('البريد الإلكتروني')->placeholder('عميل زائر (بدون حساب)'),
                            TextEntry::make('address')->label('العنوان')->placeholder('—'),
                            TextEntry::make('created_at')->label('تاريخ التسجيل')->dateTime(),
                        ]),
                    ]),
                Section::make('ملخص النشاط')
                    ->description('ملخص الطلبات والمبالغ المرتبطة بالعميل.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('orders_count')
                                ->label('عدد الطلبات')
                                ->state(fn ($record): int => $record->orders_count ?? $record->orders()->count()),
                            TextEntry::make('confirmed_orders_sum_total')
                                ->label('إجمالي المصروف')
                                ->numeric(2)
                                ->state(fn ($record): float => (float) ($record->confirmed_orders_sum_total ?? $record->confirmedOrders()->sum('total'))),
                            TextEntry::make('orders_max_created_at')
                                ->label('تاريخ آخر طلب')
                                ->dateTime()
                                ->placeholder('—')
                                ->state(fn ($record) => $record->orders_max_created_at ?? $record->orders()->latest()->value('created_at')),
                        ]),
                    ]),
            ]);
    }
}
