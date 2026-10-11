<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Existing shops get the new `audit.view` permission on their Owner role only; roles are added to, never reset. */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('audit.view', 'web');

        Role::query()->where('name', 'Owner')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo('audit.view'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'audit.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
