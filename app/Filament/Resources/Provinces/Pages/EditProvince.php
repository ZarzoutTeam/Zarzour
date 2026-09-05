<?php

namespace App\Filament\Resources\Provinces\Pages;

use App\Filament\Resources\Concerns\RedirectsToPreviousListPage;
use App\Filament\Resources\Provinces\ProvinceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProvince extends EditRecord
{
    use RedirectsToPreviousListPage;

    protected static string $resource = ProvinceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
