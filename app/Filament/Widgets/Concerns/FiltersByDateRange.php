<?php

namespace App\Filament\Widgets\Concerns;

use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait FiltersByDateRange
{
    use InteractsWithPageFilters;

    protected function rangeStart(): ?Carbon
    {
        return match ($this->pageFilters['range'] ?? 'all') {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }
}
