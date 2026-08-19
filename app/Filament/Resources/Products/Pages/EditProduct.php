<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Product $record, DeleteAction $action): void {
                    if (! $record->hasDeletionBlockingRelations()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('لا يمكن حذف المنتج')
                        ->body('هذا المنتج مرتبط بطلبات أو حركات مخزون أو خصومات أو عروض. ألغِ تفعيله بدلًا من حذفه، أو أزل الارتباطات غير التاريخية أولًا.')
                        ->send();

                    $action->halt();
                }),
        ];
    }
}
