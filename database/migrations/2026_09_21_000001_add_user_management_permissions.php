<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'ViewAny:User',
        'View:User',
        'Create:User',
        'Update:User',
        'Delete:User',
        'DeleteAny:User',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->permissions)
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->get()
            ->each
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
