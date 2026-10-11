<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\Warehouse;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets a shop up so it is usable on day one: the tenant, its roles, an owner, a default warehouse and units.
 * Safe to run again for the same slug or owner email (the demo seeder relies on that).
 */
class ProvisionTenantAction
{
    public const PERMISSIONS = [
        'dashboard.view', 'pos.access', 'inventory.view', 'sales.view',
        'purchases.view', 'people.view', 'reports.view', 'settings.manage',
        'products.view', 'products.manage', 'categories.view', 'categories.manage',
        'brands.view', 'brands.manage', 'units.view', 'units.manage',
        'warehouses.view', 'warehouses.manage', 'inventory.manage',
        'sales.manage', 'customers.view', 'customers.manage',
        'purchases.manage', 'suppliers.view', 'suppliers.manage',
        'expenses.view', 'expenses.manage', 'till.close', 'till.view', 'audit.view',
    ];

    /** @return array<string, list<string>> */
    public static function rolePermissions(): array
    {
        $all = self::PERMISSIONS;

        return [
            'Owner' => $all,
            'Manager' => array_values(array_diff($all, ['settings.manage', 'audit.view'])),
            'Cashier' => ['dashboard.view', 'pos.access', 'sales.view', 'products.view', 'customers.view', 'customers.manage', 'till.close'],
            'Storekeeper' => [
                'dashboard.view', 'inventory.view', 'inventory.manage', 'products.view', 'products.manage',
                'categories.view', 'brands.view', 'units.view', 'warehouses.view',
                'purchases.view', 'purchases.manage', 'suppliers.view', 'suppliers.manage',
            ],
            'Accountant' => ['dashboard.view', 'sales.view', 'sales.manage', 'customers.view', 'purchases.view', 'suppliers.view', 'reports.view', 'products.view', 'inventory.view', 'expenses.view', 'expenses.manage', 'till.view'],
        ];
    }

    /** @param  array{name: string, slug: string, domain?: string|null, plan?: string|null, status?: string, paid_until?: mixed, trial_ends_at?: mixed, owner_name: string, owner_email: string, owner_password?: string|null}  $data */
    public function handle(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            foreach (self::PERMISSIONS as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'domain' => $data['domain'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'plan' => $data['plan'] ?? config('plans.default'),
                    'paid_until' => $data['paid_until'] ?? null,
                    'trial_ends_at' => $data['trial_ends_at']
                        ?? (($data['status'] ?? null) === 'trial' ? now()->addDays((int) config('plans.trial_days')) : null),
                    'settings' => ['currency' => config('pos.default_currency'), 'locale' => 'en', 'features' => []],
                ],
            );

            $registrar = app(PermissionRegistrar::class);

            app(TenantContext::class)->run($tenant, function () use ($tenant, $data, $registrar): void {
                $registrar->setPermissionsTeamId($tenant->getKey());
                $registrar->forgetCachedPermissions();

                foreach (self::rolePermissions() as $name => $abilities) {
                    $role = Role::query()->firstOrCreate([
                        'name' => $name,
                        'guard_name' => 'web',
                        'tenant_id' => $tenant->getKey(),
                    ]);
                    $role->syncPermissions($abilities);
                }

                $owner = User::query()->withoutGlobalScope('tenant')->firstOrCreate(
                    ['email' => $data['owner_email']],
                    [
                        'name' => $data['owner_name'],
                        'password' => ($data['owner_password'] ?? null) ?: Str::random(48),
                    ],
                );
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

            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();

            return $tenant;
        });
    }
}
