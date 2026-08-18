<?php

namespace App\Filament\Resources\HomepageSettings\Tables;

use App\Models\HomepageSetting;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomepageSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('hero_image')
                    ->label('المعاينة')
                    ->collection('hero_image')
                    ->conversion('thumbnail'),
                TextColumn::make('hero_media_type')
                    ->label('نوع الغلاف')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'video' ? 'فيديو' : 'صورة'),
                IconColumn::make('hero_enabled')
                    ->label('الغلاف ظاهر')
                    ->boolean(),
                TextColumn::make('usd_to_syp_rate')
                    ->label('سعر الصرف')
                    ->numeric(2)
                    ->suffix(' ل.س / دولار')
                    ->placeholder('غير محدد'),
                TextColumn::make('payment_methods')
                    ->label('طرق الدفع')
                    ->state(function (HomepageSetting $record): string {
                        $configured = HomepageSetting::configuredPaymentMethods();

                        return collect($record->payment_methods ?? [])
                            ->map(fn (string $key): string => $configured[$key]['label'] ?? $key)
                            ->implode('، ');
                    })
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('آخر تعديل')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
