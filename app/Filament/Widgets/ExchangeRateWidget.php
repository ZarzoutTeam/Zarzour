<?php

namespace App\Filament\Widgets;

use App\Models\HomepageSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class ExchangeRateWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.widgets.exchange-rate-widget';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?float $savedRate = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('Update:HomepageSetting') ?? false;
    }

    public function mount(): void
    {
        $rate = HomepageSetting::query()->value('usd_to_syp_rate');
        $this->savedRate = $rate !== null ? (float) $rate : null;

        $this->form->fill([
            'usd_to_syp_rate' => $this->savedRate,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('usd_to_syp_rate')
                    ->label('سعر الدولار الجديد')
                    ->helperText('أدخل قيمة دولار واحد بالليرة السورية.')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->suffix('ل.س')
                    ->extraInputAttributes([
                        'dir' => 'ltr',
                        'inputmode' => 'decimal',
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canView(), 403);

        $data = $this->form->getState();

        $settings = HomepageSetting::query()->firstOrNew(['key' => 'default']);
        $settings->usd_to_syp_rate = $data['usd_to_syp_rate'];
        $settings->save();

        $this->savedRate = (float) $settings->usd_to_syp_rate;
        $this->form->fill([
            'usd_to_syp_rate' => $this->savedRate,
        ]);

        Notification::make()
            ->title('تم تحديث سعر الصرف')
            ->body('أُعيد حساب أسعار المنتجات بالليرة السورية وفق السعر الجديد.')
            ->success()
            ->send();
    }
}
