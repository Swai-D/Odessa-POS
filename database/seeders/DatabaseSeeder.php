<?php

namespace Database\Seeders;

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
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = User::query()->withoutGlobalScope('tenant')->firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@example.test')],
            [
                'name' => 'Odessa Super Admin',
                'password' => env('SUPER_ADMIN_PASSWORD') ?: Str::random(48),
            ],
        );
        if ($password = env('SUPER_ADMIN_PASSWORD')) {
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
                'Cashier' => ['dashboard.view', 'pos.access', 'sales.view'],
                'Storekeeper' => ['dashboard.view', 'inventory.view'],
                'Accountant' => ['dashboard.view', 'sales.view', 'purchases.view', 'reports.view'],
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
                ['email' => env('DEMO_OWNER_EMAIL', 'owner@demo.test')],
                [
                    'name' => 'Demo Owner',
                    'password' => env('DEMO_OWNER_PASSWORD') ?: Str::random(48),
                ],
            );
            if ($password = env('DEMO_OWNER_PASSWORD')) {
                $owner->password = $password;
            }
            $owner->forceFill(['tenant_id' => $tenant->getKey(), 'is_super_admin' => false, 'locale' => 'en'])->save();
            $owner->assignRole('Owner');
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
