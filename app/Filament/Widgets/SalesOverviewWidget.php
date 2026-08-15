<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\FiltersByDateRange;
use App\Models\Customer;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverviewWidget extends StatsOverviewWidget
{
    use FiltersByDateRange;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $query = Order::query()
            ->when($this->rangeStart(), fn ($q, $start) => $q->where('created_at', '>=', $start));

        $totalSales = (clone $query)->whereIn('status', Customer::CONFIRMED_STATUSES)->sum('total');

        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            Stat::make('إجمالي المبيعات', number_format((float) $totalSales, 2).' ل.س')
                ->color('success'),
        ];

        foreach (OrderStatus::labels() as $status => $label) {
            $stats[] = Stat::make($label, (int) ($statusCounts[$status] ?? 0));
        }

        return $stats;
    }
}
