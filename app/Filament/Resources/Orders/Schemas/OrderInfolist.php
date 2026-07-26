<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    private const PAYMENT_METHOD_LABELS = [
        'cod' => 'الدفع عند الاستلام',
        'sham_cash' => 'شام كاش',
        'visa_ui' => 'فيزا',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات العميل')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('customer_name')->label('اسم العميل'),
                            TextEntry::make('phone_number')->label('رقم الهاتف'),
                            TextEntry::make('user.name')->label('حساب مرتبط')->placeholder('طلب زائر (بدون حساب)'),
                            TextEntry::make('province.name')->label('المحافظة'),
                        ]),
                        TextEntry::make('shipping_address')->label('عنوان الشحن')->columnSpanFull(),
                        TextEntry::make('extra_notes')->label('ملاحظات إضافية')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('حالة الطلب')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('status')
                                ->label('الحالة')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => OrderStatus::from($state)->getLabel())
                                ->color(fn (string $state): string => OrderStatus::from($state)->getColor()),
                            TextEntry::make('payment_method')
                                ->label('طريقة الدفع')
                                ->formatStateUsing(fn (string $state): string => self::PAYMENT_METHOD_LABELS[$state] ?? $state),
                            TextEntry::make('created_at')->label('تاريخ الطلب')->dateTime(),
                        ]),
                    ]),
                Section::make('عناصر الطلب')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(5)->schema([
                                    TextEntry::make('product_name_snapshot')->label('المنتج'),
                                    TextEntry::make('quantity')->label('الكمية'),
                                    TextEntry::make('unit_price_snapshot')->label('سعر الوحدة')->numeric(2),
                                    TextEntry::make('line_total')->label('الإجمالي')->numeric(2),
                                    IconEntry::make('is_gift')->label('هدية؟')->boolean(),
                                ]),
                            ]),
                    ]),
                Section::make('ملخص الفاتورة')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('subtotal')->label('المجموع الفرعي')->numeric(2),
                            TextEntry::make('discount_amount')->label('قيمة الخصم')->numeric(2),
                            TextEntry::make('shipping_fee')->label('رسوم الشحن')->numeric(2),
                            TextEntry::make('total')->label('الإجمالي الكلي')->numeric(2),
                            TextEntry::make('coupon.code')->label('كود الخصم المستخدم')->placeholder('—'),
                            TextEntry::make('appliedOffer.type')->label('العرض المطبّق')->placeholder('—'),
                        ]),
                    ]),
            ]);
    }
}
