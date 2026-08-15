<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getSubheading(): ?string
    {
        return 'ملخص سريع للمبيعات والطلبات والمنتجات الأكثر طلباً.';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'zs-dashboard',
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('range')
                    ->label('الفترة المعروضة')
                    ->options([
                        'all' => 'الكل',
                        'today' => 'اليوم',
                        'week' => 'هذا الأسبوع',
                        'month' => 'هذا الشهر',
                    ])
                    ->default('all')
                    ->helperText('يُحدّث اختيارك جميع الإحصاءات والجداول في هذه الصفحة.')
                    ->selectablePlaceholder(false),
            ]);
    }
}
