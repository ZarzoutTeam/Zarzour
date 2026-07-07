<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('الرابط (Slug)')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('يُولَّد تلقائياً من الاسم، ويمكن تعديله يدوياً.'),
                Select::make('parent_id')
                    ->label('الفئة الأب')
                    ->options(
                        fn (?Category $record) => Category::query()
                            ->whereNull('parent_id')
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->orderBy('sort_order')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->native(false)
                    ->helperText('اتركه فارغاً لجعل هذه فئة رئيسية.'),
                TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('مفعّلة')
                    ->default(true)
                    ->required(),
            ]);
    }
}
