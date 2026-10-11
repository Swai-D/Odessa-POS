<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Services\AuditRecorder;
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
            $before = $role?->permissions->pluck('name')->sort()->values()->all();
            $event = $role === null ? 'created' : 'updated';

            $role ??= Role::query()->create([
                'name' => $data['name'],
                'guard_name' => 'web',
                'tenant_id' => app(TenantContext::class)->get()?->getKey(),
            ]);

            $role->forceFill(['name' => $data['name']])->save();
            $role->syncPermissions($data['permissions']);

            $after = collect($data['permissions'])->sort()->values()->all();
            app(AuditRecorder::class)->note($event, 'Role', $role->getKey(), $role->name, [
                'permissions' => ['old' => $before === null ? null : implode(', ', $before), 'new' => implode(', ', $after)],
            ]);

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
