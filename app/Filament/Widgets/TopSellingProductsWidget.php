<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltersByDateRange;
use App\Models\Customer;
use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopSellingProductsWidget extends TableWidget
{
    use FiltersByDateRange;

    protected static bool $isLazy = false;

    protected static ?string $heading = 'الأكثر مبيعاً';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTopSellingQuery())
            ->columns([
                TextColumn::make('product.name')->label('المنتج'),
                TextColumn::make('total_quantity')->label('الكمية المباعة'),
            ])
            ->defaultKeySort(false)
            ->paginated(false)
            ->emptyStateHeading('لا توجد مبيعات في هذه الفترة');
    }

    private function getTopSellingQuery(): Builder
    {
        return OrderItem::query()
            ->select([
                DB::raw('MIN(order_items.id) as id'),
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
            ])
            ->where('order_items.is_gift', false)
            ->whereHas('order', function (Builder $query): void {
                $query->whereIn('status', Customer::CONFIRMED_STATUSES);

                if ($start = $this->rangeStart()) {
                    $query->where('created_at', '>=', $start);
                }
            })
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->with('product');
    }
}
