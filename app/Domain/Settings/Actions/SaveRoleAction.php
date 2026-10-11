<?php

namespace App\Domain\Settings\Actions;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Creates or updates a custom role of the current shop. The five default roles are never touched here. */
class SaveRoleAction
{
    /** @param  array{name: string, permissions: list<string>}  $data */
    public function handle(?Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $role ??= Role::query()->create([
                'name' => $data['name'],
                'guard_name' => 'web',
                'tenant_id' => app(TenantContext::class)->get()?->getKey(),
            ]);

            $role->forceFill(['name' => $data['name']])->save();
            $role->syncPermissions($data['permissions']);

            return $role;
        });
    }

    /** The default roles every shop starts with; they cannot be renamed, edited or deleted. */
    public static function systemRoleNames(): array
    {
        return array_keys(ProvisionTenantAction::rolePermissions());
    }

    /** The custom role with this id in the current shop, or null. */
    public static function find(int|string $id): ?Role
    {
        return Role::query()
            ->where('tenant_id', app(TenantContext::class)->get()?->getKey())
            ->whereNotIn('name', self::systemRoleNames())
            ->find($id);
    }
}
