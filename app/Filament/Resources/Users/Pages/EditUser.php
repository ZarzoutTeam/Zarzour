<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\RedirectsToPreviousListPage;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use RedirectsToPreviousListPage;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
