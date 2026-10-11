<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function roleShop(string $slug, string $plan = 'enterprise', array $permissions = ['settings.manage']): array
{
    $tenant = createTenant($slug, $plan);
    $owner = createTenantUser($tenant, $permissions);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    foreach (['Owner', 'Cashier'] as $name) {
        Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenant->getKey()]);
    }

    return [$tenant, $owner];
}

function customRole(int|string $tenantId, string $name = 'Supervisor', array $permissions = ['sales.view']): Role
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
    $role = Role::query()->create(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $role->syncPermissions($permissions);

    return $role;
}

function rolePayload(array $overrides = []): array
{
    return array_merge(['name' => 'Supervisor', 'permissions' => ['sales.view', 'customers.view']], $overrides);
}

it('lets an owner on Enterprise create a custom role with chosen permissions', function () {
    [$tenant, $owner] = roleShop('role-a');

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-a')->post('/roles', rolePayload())->assertRedirect('/roles');

    $role = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Supervisor')->firstOrFail();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['customers.view', 'sales.view']);
});

it('offers the custom role when adding a user', function () {
    [$tenant, $owner] = roleShop('role-b');
    customRole($tenant->getKey());

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-b')->post('/users', [
        'name' => 'Neema', 'email' => 'neema@example.com', 'role' => 'Supervisor', 'password' => 'secret-pass-1',
    ])->assertSessionHasNoErrors();

    $user = User::query()->withoutGlobalScope('tenant')->where('email', 'neema@example.com')->firstOrFail();
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    expect($user->fresh()->hasRole('Supervisor'))->toBeTrue()->and($user->fresh()->can('sales.view'))->toBeTrue();
});

it('shows the roles page on Enterprise', function () {
    [, $owner] = roleShop('role-c');

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-c')->get('/roles')->assertOk()->assertSee('Cashier');
});

it('keeps custom roles out of Basic and Medium shops', function (string $plan) {
    [, $owner] = roleShop('role-d', $plan);

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-d')->get('/roles')->assertForbidden();
})->with(['basic', 'medium']);

it('refuses to create a role on a plan without custom roles', function () {
    [, $owner] = roleShop('role-e', 'medium');

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-e')->post('/roles', rolePayload())->assertForbidden();
});

it('needs the settings permission', function () {
    [, $staff] = roleShop('role-f', 'enterprise', ['sales.view']);

    $this->actingAs($staff)->withHeader('X-Tenant', 'role-f')->get('/roles')->assertForbidden();
});

it('validates the name and the permissions', function (array $payload, string $field) {
    [$tenant, $owner] = roleShop('role-g');
    customRole($tenant->getKey(), 'Taken');

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-g')->post('/roles', rolePayload($payload))->assertSessionHasErrors($field);
})->with([
    'default name' => [['name' => 'Cashier'], 'name'],
    'taken name' => [['name' => 'Taken'], 'name'],
    'empty name' => [['name' => ''], 'name'],
    'no permissions' => [['permissions' => []], 'permissions'],
    'unknown permission' => [['permissions' => ['sales.view', 'nope.delete']], 'permissions.1'],
]);

it('edits a custom role and replaces its permissions', function () {
    [$tenant, $owner] = roleShop('role-h');
    $role = customRole($tenant->getKey());

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-h')
        ->put('/roles/'.$role->getKey(), rolePayload(['name' => 'Supervisor', 'permissions' => ['products.view']]))
        ->assertRedirect('/roles');

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['products.view']);
});

it('never changes or deletes a default role', function () {
    [$tenant, $owner] = roleShop('role-i');
    $cashier = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Cashier')->firstOrFail();

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-i')
        ->put('/roles/'.$cashier->getKey(), rolePayload(['name' => 'Renamed']))->assertNotFound();
});

it('does not delete a default role', function () {
    [$tenant, $owner] = roleShop('role-j');
    $cashier = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Cashier')->firstOrFail();

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-j')->delete('/roles/'.$cashier->getKey())->assertNotFound();
    expect(Role::query()->whereKey($cashier->getKey())->exists())->toBeTrue();
});

it('deletes an unused custom role', function () {
    [$tenant, $owner] = roleShop('role-k');
    $role = customRole($tenant->getKey());

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-k')->delete('/roles/'.$role->getKey())->assertRedirect('/roles');

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeFalse();
});

it('refuses to delete a custom role that still has users', function () {
    [$tenant, $owner] = roleShop('role-l');
    $role = customRole($tenant->getKey());
    $user = createTenantUser($tenant, ['sales.view']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles(['Supervisor']);

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-l')->delete('/roles/'.$role->getKey())->assertSessionHasErrors('delete');

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeTrue();
});

it('cannot touch the roles of another shop', function () {
    [, $owner] = roleShop('role-m');
    [$other] = roleShop('role-n');
    $foreign = customRole($other->getKey(), 'Foreign');

    $this->actingAs($owner)->withHeader('X-Tenant', 'role-m')
        ->put('/roles/'.$foreign->getKey(), rolePayload())->assertNotFound();
});

it('lets two shops use the same custom role name', function () {
    [$a, $ownerA] = roleShop('role-o');
    [$b] = roleShop('role-p');
    customRole($b->getKey(), 'Supervisor');

    $this->actingAs($ownerA)->withHeader('X-Tenant', 'role-o')->post('/roles', rolePayload())->assertSessionHasNoErrors();

    expect(Role::query()->where('name', 'Supervisor')->count())->toBe(2);
});

it('shows the roles entry locked in the Medium sidebar', function () {
    [, $owner] = roleShop('role-q', 'medium');

    $html = $this->actingAs($owner)->withHeader('X-Tenant', 'role-q')->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('<a href="'.route('roles.index').'" class="text-muted" data-locked="1"');
});
