<?php

namespace App\Filament\Resources\Offers;

use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Offers\Schemas\OfferForm;
use App\Filament\Resources\Offers\Tables\OffersTable;
use App\Models\Offer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $modelLabel = 'عرض';

    protected static ?string $pluralModelLabel = 'العروض';

    protected static ?string $navigationLabel = 'العروض';

    protected static string|\UnitEnum|null $navigationGroup = 'التسويق والعروض';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return OfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OffersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product', 'gifts.giftProduct']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
            'create' => CreateOffer::route('/create'),
            'edit' => EditOffer::route('/{record}/edit'),
        ];
    }
}
