<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

function ownerOf(string $slug, string $plan): array
{
    $tenant = createTenant($slug, $plan);
    $owner = createTenantUser($tenant, ['settings.manage', 'warehouses.view', 'warehouses.manage']);
    app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    foreach (['Owner', 'Cashier'] as $name) {
        Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenant->getKey()]);
    }
    $owner->assignRole('Owner');

    return [$tenant, $owner];
}

function staffPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Asha', 'email' => 'asha@example.com', 'role' => 'Cashier', 'password' => 'secret-pass-1'], $overrides);
}

it('lets an owner add a user with a role', function () {
    [$tenant, $owner] = ownerOf('shop-u', 'medium');

    $this->actingAs($owner)->withHeader('X-Tenant', 'shop-u')->post('/users', staffPayload())->assertRedirect('/users');

    $asha = User::query()->withoutGlobalScope('tenant')->where('email', 'asha@example.com')->firstOrFail();
    expect($asha->tenant_id)->toBe($tenant->getKey());
    app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    expect($asha->fresh()->hasRole('Cashier'))->toBeTrue();
});

it('refuses users beyond the plan limit', function () {
    [, $owner] = ownerOf('shop-l', 'basic');
    $headers = ['X-Tenant' => 'shop-l'];

    $this->actingAs($owner)->withHeaders($headers)->post('/users', staffPayload(['email' => 'a@example.com']))->assertSessionHasNoErrors();
    $this->actingAs($owner)->withHeaders($headers)->post('/users', staffPayload(['email' => 'b@example.com']))->assertSessionHasNoErrors();
    // Owner + 2 = 3 users, the Basic limit.
    $this->actingAs($owner)->withHeaders($headers)->post('/users', staffPayload(['email' => 'c@example.com']))->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(3);
});

it('does not let an owner touch another shop or lack the permission', function () {
    [, $ownerA] = ownerOf('shop-a1', 'enterprise');
    ownerOf('shop-b1', 'enterprise');
    $other = User::query()->withoutGlobalScope('tenant')->where('tenant_id', '!=', $ownerA->tenant_id)->firstOrFail();

    $this->actingAs($ownerA)->withHeader('X-Tenant', 'shop-a1')->delete("/users/{$other->getKey()}")->assertNotFound();

    $tenant = createTenant('shop-c1');
    $cashier = createTenantUser($tenant, ['pos.access']);
    $this->actingAs($cashier)->withHeader('X-Tenant', 'shop-c1')->get('/users')->assertForbidden();
});

it('protects the last owner and the signed-in user from deletion', function () {
    [, $owner] = ownerOf('shop-o', 'enterprise');

    $this->actingAs($owner)->withHeader('X-Tenant', 'shop-o')->delete("/users/{$owner->getKey()}")->assertSessionHasErrors('delete');
    $this->actingAs($owner)->withHeader('X-Tenant', 'shop-o')
        ->put("/users/{$owner->getKey()}", staffPayload(['email' => $owner->email, 'role' => 'Cashier']))
        ->assertSessionHasErrors('role');

    expect(User::query()->whereKey($owner->getKey())->exists())->toBeTrue();
});

it('limits warehouses by plan', function () {
    [, $owner] = ownerOf('shop-w', 'basic');
    $payload = fn (string $code) => ['name' => 'W '.$code, 'code' => $code, 'is_default' => 0, 'is_active' => 1];

    $this->actingAs($owner)->withHeader('X-Tenant', 'shop-w')->post('/warehouses', $payload('A'))->assertSessionHasNoErrors();
    $this->actingAs($owner)->withHeader('X-Tenant', 'shop-w')->post('/warehouses', $payload('B'))->assertSessionHasErrors('limit');
});
