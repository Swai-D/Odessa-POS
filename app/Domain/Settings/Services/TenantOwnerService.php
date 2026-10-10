<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantOwnerService
{
    public function find(Tenant $tenant): ?User
    {
        return $this->withinTenant($tenant, fn (): ?User => $this->findInCurrentTenant());
    }

    /** @param array{name: string, email: string, password?: string|null} $data */
    public function update(Tenant $tenant, array $data): User
    {
        return DB::transaction(fn (): User => $this->withinTenant($tenant, function () use ($tenant, $data): User {
            $owner = $this->findInCurrentTenant();
            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (($data['password'] ?? '') !== '') {
                $attributes['password'] = $data['password'];
            }

            if ($owner !== null) {
                $owner->forceFill($attributes)->save();

                return $owner;
            }

            $owner = User::query()->create($attributes);
            $permissions = ProvisionTenantAction::rolePermissions()['Owner'];

            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $role = Role::query()->firstOrCreate([
                'name' => 'Owner',
                'guard_name' => 'web',
                'tenant_id' => $tenant->getKey(),
            ]);
            $role->syncPermissions($permissions);
            $owner->assignRole($role);

            return $owner;
        }));
    }

    private function findInCurrentTenant(): ?User
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Owner'))
            ->orderBy('id')
            ->first();
    }

    private function withinTenant(Tenant $tenant, \Closure $callback): mixed
    {
        $context = app(TenantContext::class);
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            return $context->run($tenant, function () use ($tenant, $registrar, $callback): mixed {
                $registrar->setPermissionsTeamId($tenant->getKey());
                $registrar->forgetCachedPermissions();

                return $callback();
            });
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $registrar->forgetCachedPermissions();
        }
    }
}
