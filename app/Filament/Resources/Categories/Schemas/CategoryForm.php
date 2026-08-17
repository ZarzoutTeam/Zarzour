<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Support\CatalogImageUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الفئة')
                    ->description('حدد اسم الفئة ومكانها ضمن شجرة أقسام المتجر.')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم الفئة')
                            ->placeholder('مثال: الأحذية الرياضية')
                            ->helperText('يظهر هذا الاسم في قائمة الفئات وصفحات المنتجات.')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('معرّف رابط الفئة')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('يُولّد تلقائياً من الاسم ويُستخدم داخل رابط الفئة. لا تستخدم مسافات.'),
                        Select::make('parent_id')
                            ->label('الفئة الرئيسية')
                            ->options(
                                fn (?Category $record) => Category::query()
                                    ->whereNull('parent_id')
                                    ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->native(false)
                            ->placeholder('بدون — فئة رئيسية')
                            ->helperText('اتركه فارغاً لإنشاء فئة رئيسية، أو اختر فئة لتظهر هذه الفئة بداخلها.'),
                        TextInput::make('sort_order')
                            ->label('ترتيب الظهور')
                            ->helperText('الرقم الأصغر يظهر أولاً بين الفئات من المستوى نفسه.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('حالة الفئة')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('الفئة ظاهرة في المتجر')
                            ->helperText('عند التعطيل لن تظهر الفئة ضمن قوائم المتجر للعملاء.')
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpanFull(),
                Section::make('صورة الفئة')
                    ->description('الصورة التي تمثل الفئة في الصفحة الرئيسية وقائمة الفئات.')
                    ->schema([
                        CatalogImageUpload::configure(SpatieMediaLibraryFileUpload::make('image')
                            ->label('الصورة')
                            ->collection('image')
                            ->conversion('medium'))
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                null,
                                '1:1',
                                '4:3',
                                '3:2',
                                '16:9',
                            ])
                            ->openable()
                            ->downloadable()
                            ->helperText(CatalogImageUpload::limitsDescription().' اختيارية، ويُنصح بصورة مربعة أو أفقية واضحة.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
