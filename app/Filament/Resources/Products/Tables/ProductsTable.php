<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')
                    ->label('الصورة')
                    ->collection('images')
                    ->conversion('thumbnail'),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('الفئة')
                    ->badge()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('السعر')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->label('الكمية المتاحة')
                    ->state(fn (Product $record) => $record->available_quantity)
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('الفئة')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('الحالة'),
                SelectFilter::make('stock_status')
                    ->label('التوفر')
                    ->options([
                        'available' => 'متوفر',
                        'out_of_stock' => 'نافذ',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'available') {
                            $query->whereColumn('stock_quantity', '>', 'reserved_quantity');
                        } elseif ($data['value'] === 'out_of_stock') {
                            $query->whereColumn('stock_quantity', '<=', 'reserved_quantity');
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Product $record, DeleteAction $action) {
                        if (
                            $record->discounts()->exists()
                            || $record->offers()->exists()
                            || $record->orderItems()->exists()
                            || $record->stockMovements()->exists()
                        ) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن حذف المنتج')
                                ->body('هذا المنتج مرتبط بطلبات أو خصومات أو عروض ولا يمكن حذفه.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('تفعيل المحدد')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each(fn (Model $record) => $record->update(['is_active' => true])))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('تعطيل المحدد')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->action(fn (Collection $records) => $records->each(fn (Model $record) => $record->update(['is_active' => false])))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
