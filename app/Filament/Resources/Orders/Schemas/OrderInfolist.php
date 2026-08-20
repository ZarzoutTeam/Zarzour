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
                    ->description('معلومات التواصل والتوصيل المسجلة مع الطلب.')
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
                    ->description('تفاصيل الحالة الحالية وطريقة الدفع وتاريخ إنشاء الطلب.')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('status')
                                ->label('الحالة')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => OrderStatus::from($state)->getLabel())
                                ->color(fn (string $state): string => OrderStatus::from($state)->getColor()),
                            TextEntry::make('payment_method')
                                ->label('طريقة الدفع')
                                ->formatStateUsing(fn (string $state): string => self::PAYMENT_METHOD_LABELS[$state] ?? $state),
                            TextEntry::make('currency')
                                ->label('عملة التحصيل')
                                ->formatStateUsing(fn (?string $state): string => $state === 'USD' ? 'دولار أمريكي' : 'ليرة سورية'),
                            TextEntry::make('exchange_rate_snapshot')
                                ->label('سعر الصرف وقت الطلب')
                                ->numeric(2)
                                ->suffix(' ل.س / دولار')
                                ->placeholder('غير متوفر للطلبات القديمة'),
                            TextEntry::make('created_at')->label('تاريخ الطلب')->dateTime(),
                        ]),
                    ]),
                Section::make('عناصر الطلب')
                    ->description('المنتجات والكميات والأسعار التي ثُبتت لحظة إنشاء الطلب.')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('product_name_snapshot')->label('المنتج'),
                                    TextEntry::make('quantity')->label('الكمية'),
                                    TextEntry::make('unit_price_snapshot')->label('سعر الوحدة')->numeric(2)->suffix(' ل.س'),
                                    TextEntry::make('unit_price_snapshot_usd')->label('سعر الوحدة بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                                    TextEntry::make('line_total')->label('الإجمالي')->numeric(2)->suffix(' ل.س'),
                                    TextEntry::make('line_total_usd')->label('الإجمالي بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                                    TextEntry::make('direct_discount_amount')->label('خصم المنتج')->numeric(2)->suffix(' ل.س')->placeholder('غير متوفر'),
                                    TextEntry::make('direct_discount_amount_usd')->label('خصم المنتج بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                                    TextEntry::make('coupon_discount_amount')->label('خصم القسيمة')->numeric(2)->suffix(' ل.س')->placeholder('غير متوفر'),
                                    TextEntry::make('coupon_discount_amount_usd')->label('خصم القسيمة بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                                    TextEntry::make('offer_discount_amount')->label('خصم العرض')->numeric(2)->suffix(' ل.س')->placeholder('غير متوفر'),
                                    TextEntry::make('offer_discount_amount_usd')->label('خصم العرض بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                                    IconEntry::make('is_gift')->label('هدية؟')->boolean(),
                                ]),
                            ]),
                    ]),
                Section::make('ملخص الفاتورة')
                    ->description('تفصيل المبلغ النهائي بعد الخصومات ورسوم الشحن.')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('subtotal')->label('المجموع الفرعي')->numeric(2)->suffix(' ل.س'),
                            TextEntry::make('subtotal_usd')->label('المجموع الفرعي بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                            TextEntry::make('discount_amount')->label('قيمة الخصم')->numeric(2)->suffix(' ل.س'),
                            TextEntry::make('discount_amount_usd')->label('قيمة الخصم بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                            TextEntry::make('coupon_discount_amount')->label('خصم القسيمة')->numeric(2)->suffix(' ل.س')->placeholder('غير متوفر للطلبات القديمة'),
                            TextEntry::make('coupon_discount_amount_usd')->label('خصم القسيمة بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                            TextEntry::make('shipping_fee')->label('رسوم الشحن')->numeric(2)->suffix(' ل.س'),
                            TextEntry::make('shipping_fee_usd')->label('رسوم الشحن بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                            TextEntry::make('total')->label('الإجمالي الكلي')->numeric(2)->suffix(' ل.س'),
                            TextEntry::make('total_usd')->label('الإجمالي الكلي بالدولار')->numeric(2)->suffix(' دولار')->placeholder('—'),
                            TextEntry::make('coupon.code')->label('كود الخصم المستخدم')->placeholder('—'),
                            TextEntry::make('appliedOffer.type')
                                ->label('العرض المطبّق')
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    'discount_only' => 'خصم فقط',
                                    'discount_with_gift' => 'خصم مع هدية',
                                    'gift_only' => 'هدية فقط',
                                    default => '—',
                                })
                                ->placeholder('—'),
                        ]),
                    ]),
            ]);
    }
}
