<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function createTenant(string $slug, string $plan = 'enterprise'): Tenant
{
    return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => $plan]);
}

/**
 * Create a user that belongs to $tenant and holds exactly the given permissions.
 *
 * @param  list<string>  $permissions
 */
function createTenantUser(Tenant $tenant, array $permissions): User
{
    return app(TenantContext::class)->run($tenant, function () use ($tenant, $permissions): User {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $registrar->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::query()->create([
            'name' => 'test-'.Str::random(6),
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $user->assignRole($role);

        $registrar->setPermissionsTeamId(null);

        return $user;
    });
}

/** Permissions that let a user fully manage catalog and inventory. */
function inventoryAdminPermissions(): array
{
    return [
        'products.view', 'products.manage', 'categories.view', 'categories.manage',
        'brands.view', 'brands.manage', 'units.view', 'units.manage',
        'warehouses.view', 'warehouses.manage', 'inventory.view', 'inventory.manage',
    ];
}
