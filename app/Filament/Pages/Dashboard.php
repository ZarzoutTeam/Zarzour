<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

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
                    ->label('المدى الزمني')
                    ->options([
                        'all' => 'الكل',
                        'today' => 'اليوم',
                        'week' => 'هذا الأسبوع',
                        'month' => 'هذا الشهر',
                    ])
                    ->default('all')
                    ->selectablePlaceholder(false),
            ]);
    }
}
