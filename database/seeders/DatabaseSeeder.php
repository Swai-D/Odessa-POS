<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\Warehouse;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = [
            'dashboard.view', 'pos.access', 'inventory.view', 'sales.view',
            'purchases.view', 'people.view', 'reports.view', 'settings.manage',
            'products.view', 'products.manage', 'categories.view', 'categories.manage',
            'brands.view', 'brands.manage', 'units.view', 'units.manage',
            'warehouses.view', 'warehouses.manage', 'inventory.manage',
            'sales.manage', 'customers.view', 'customers.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = User::query()->withoutGlobalScope('tenant')->firstOrCreate(
            ['email' => config('pos.seed.super_admin_email')],
            [
                'name' => 'Odessa Super Admin',
                'password' => config('pos.seed.super_admin_password') ?: Str::random(48),
            ],
        );
        if ($password = config('pos.seed.super_admin_password')) {
            $superAdmin->password = $password;
        }
        $superAdmin->forceFill(['tenant_id' => null, 'is_super_admin' => true, 'locale' => 'en'])->save();

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Store',
                'status' => 'active',
                'plan' => 'demo',
                'settings' => ['currency' => 'TZS', 'locale' => 'en', 'features' => []],
            ],
        );

        app(TenantContext::class)->run($tenant, function () use ($tenant, $permissions): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $rolePermissions = [
                'Owner' => $permissions,
                'Manager' => array_values(array_diff($permissions, ['settings.manage'])),
                'Cashier' => ['dashboard.view', 'pos.access', 'sales.view', 'products.view', 'customers.view', 'customers.manage'],
                'Storekeeper' => [
                    'dashboard.view', 'inventory.view', 'inventory.manage', 'products.view', 'products.manage',
                    'categories.view', 'brands.view', 'units.view', 'warehouses.view',
                ],
                'Accountant' => ['dashboard.view', 'sales.view', 'sales.manage', 'customers.view', 'purchases.view', 'reports.view', 'products.view', 'inventory.view'],
            ];

            foreach ($rolePermissions as $name => $abilities) {
                $role = Role::query()->firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->getKey(),
                ]);
                $role->syncPermissions($abilities);
            }

            $owner = User::query()->firstOrCreate(
                ['email' => config('pos.seed.demo_owner_email')],
                [
                    'name' => 'Demo Owner',
                    'password' => config('pos.seed.demo_owner_password') ?: Str::random(48),
                ],
            );
            if ($password = config('pos.seed.demo_owner_password')) {
                $owner->password = $password;
            }
            $owner->forceFill(['tenant_id' => $tenant->getKey(), 'is_super_admin' => false, 'locale' => 'en'])->save();
            $owner->assignRole('Owner');

            Warehouse::query()->firstOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main Store', 'is_default' => true, 'is_active' => true],
            );

            foreach ([['Piece', 'pcs', false], ['Kilogram', 'kg', true], ['Litre', 'l', true]] as [$name, $short, $decimal]) {
                Unit::query()->firstOrCreate(['name' => $name], ['short_name' => $short, 'allow_decimal' => $decimal]);
            }
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
