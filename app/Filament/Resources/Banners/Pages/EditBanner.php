<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Concerns\RedirectsToPreviousListPage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBanner extends EditRecord
{
    use RedirectsToPreviousListPage;

    protected static string $resource = BannerResource::class;

    protected function afterSave(): void
    {
        $this->getRecord()->clearInactiveMedia();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
