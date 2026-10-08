<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shops created before expenses existed already have their roles. Give the finance roles the new permissions;
 * roles are only added to, never reset, so any change a shop made to its roles is kept.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Owner' => ['expenses.view', 'expenses.manage'],
        'Manager' => ['expenses.view', 'expenses.manage'],
        'Accountant' => ['expenses.view', 'expenses.manage'],
    ];

    public function up(): void
    {
        foreach (['expenses.view', 'expenses.manage'] as $permission) {
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

        Permission::query()->whereIn('name', ['expenses.view', 'expenses.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
