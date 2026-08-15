<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label('الفئة الأب')
                    ->placeholder('— فئة رئيسية —')
                    ->badge(),
                TextColumn::make('products_count')
                    ->label('عدد المنتجات')
                    ->counts('products')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean(),
                TextColumn::make('display_location')
                    ->label('مكان الظهور')
                    ->state(fn (Category $record): string => match (true) {
                        ! $record->is_active => 'غير ظاهرة',
                        $record->parent_id === null => 'رئيسية',
                        ! $record->parent?->is_active => 'الفئة الأب غير مفعّلة',
                        default => 'ضمن '.($record->parent?->name ?? 'الفئة الأب'),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'غير ظاهرة', 'الفئة الأب غير مفعّلة' => 'danger',
                        default => 'success',
                    }),
                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة'),
                Filter::make('roots_only')
                    ->label('الفئات الرئيسية فقط')
                    ->query(fn ($query) => $query->whereNull('parent_id'))
                    ->toggle(),
                SelectFilter::make('parent_id')
                    ->label('فئة فرعية لـ')
                    ->options(fn () => Category::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Category $record, DeleteAction $action) {
                        if ($record->products()->exists() || $record->children()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن حذف الفئة')
                                ->body('هذه الفئة مرتبطة بمنتجات أو فئات فرعية. احذفها أو انقلها أولاً.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
