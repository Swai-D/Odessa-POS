<?php

use App\Domain\Catalog\Models\Category;
use App\Models\User;

function superAdmin(): User
{
    $user = User::factory()->create(['tenant_id' => null]);
    $user->forceFill(['is_super_admin' => true])->save();

    return $user;
}

it('refuses shop writes without a tenant instead of failing in the database', function (): void {
    $admin = superAdmin();

    $this->actingAs($admin)->post('/categories', ['name' => 'Nafaka', 'is_active' => 1])->assertForbidden();
    $this->actingAs($admin)->postJson('/pos/checkout', [])->assertForbidden()->assertJsonStructure(['message']);

    expect(Category::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('explains why on shop pages and still shows the dashboard', function (): void {
    $admin = superAdmin();

    $this->actingAs($admin)->get('/products')->assertForbidden()->assertSee('shop', false);
    $this->actingAs($admin)->get('/dashboard')->assertOk();
});

it('lets a shop user through as before', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['categories.view', 'categories.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/categories', ['name' => 'Nafaka', 'is_active' => 1])
        ->assertSessionHasNoErrors();
});
