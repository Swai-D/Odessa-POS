<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shops created before till closing existed already have their roles. Give them the new permissions; roles are
 * only added to, never reset, so any change a shop made to its roles is kept.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Owner' => ['till.close', 'till.view'],
        'Manager' => ['till.close', 'till.view'],
        'Cashier' => ['till.close'],
        'Accountant' => ['till.view'],
    ];

    public function up(): void
    {
        foreach (['till.close', 'till.view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::GRANTS as $role => $permissions) {
            Role::query()->where('name', $role)->where('guard_name', 'web')->get()
                ->each(fn (Role $r) => $r->givePermissionTo($permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::GRANTS as $role => $permissions) {
            Role::query()->where('name', $role)->where('guard_name', 'web')->get()
                ->each(fn (Role $r) => $r->revokePermissionTo($permissions));
        }

        Permission::query()->whereIn('name', ['till.close', 'till.view'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
