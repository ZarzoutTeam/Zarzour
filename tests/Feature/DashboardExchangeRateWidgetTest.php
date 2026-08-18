<?php

namespace Tests\Feature;

use App\Filament\Widgets\ExchangeRateWidget;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardExchangeRateWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_exchange_rate_from_dashboard_widget(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        $product = Product::factory()->create([
            'price' => 1,
            'price_usd' => 10,
        ]);

        Livewire::test(ExchangeRateWidget::class)
            ->set('data.usd_to_syp_rate', 12500)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            12500.0,
            (float) HomepageSetting::query()->value('usd_to_syp_rate'),
        );
        $this->assertSame(125000.0, (float) $product->refresh()->price_syp);
    }

    public function test_user_without_settings_permission_cannot_update_exchange_rate(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        Livewire::test(ExchangeRateWidget::class)
            ->set('data.usd_to_syp_rate', 12500)
            ->call('save')
            ->assertForbidden();
    }
}
