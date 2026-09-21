<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_super_admin_can_list_and_create_a_dashboard_user_with_a_role(): void
    {
        $managerRole = Role::findByName('manager');

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$this->admin]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Dashboard Manager',
                'email' => 'manager@example.com',
                'phone_number' => '0912345678',
                'roles' => [$managerRole->getKey()],
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'manager@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('manager'));
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertTrue($user->canAccessPanel(Filament::getCurrentPanel()));
    }

    public function test_editing_a_user_without_a_password_keeps_the_existing_password(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $user->assignRole('manager');
        $password = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Name', $user->refresh()->name);
        $this->assertSame($password, $user->password);
    }

    public function test_customer_only_accounts_cannot_access_the_panel_but_custom_staff_roles_can(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $staffRole = Role::create(['name' => 'catalog-editor', 'guard_name' => 'web']);
        $staff = User::factory()->create();
        $staff->assignRole($staffRole);

        $this->assertFalse($customer->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertTrue($staff->canAccessPanel(Filament::getCurrentPanel()));
    }

    public function test_current_user_and_last_super_admin_cannot_be_deleted(): void
    {
        $policy = app(UserPolicy::class);

        $this->assertFalse($policy->delete($this->admin, $this->admin));

        $replacementAdmin = User::factory()->create();
        $replacementAdmin->assignRole('super-admin');

        $this->assertTrue($policy->delete($this->admin, $replacementAdmin));

        $this->admin->removeRole('super-admin');

        $this->assertFalse($policy->delete($replacementAdmin, $replacementAdmin));
    }
}
