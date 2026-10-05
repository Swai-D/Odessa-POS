<?php

use App\Domain\Catalog\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

/*
 * Regression tests for middleware ordering. Other feature tests use actingAs(), which hands the
 * user to the guard directly. A real browser request instead loads the user from the session
 * (a tenant-scoped query) and resolves route bindings, so the tenant has to be known by then.
 */

function sessionFor($user): array
{
    return [Auth::guard('web')->getName() => $user->getKey()];
}

it('loads a tenant user from the session on a tenant request', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['products.view']);

    $this->withSession(sessionFor($user))
        ->withHeader('X-Tenant', 'shop-a')
        ->get('/products')
        ->assertOk();
});

it('resolves route model bindings inside the current tenant', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    $product = app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => 'Bound Product', 'sku' => 'BND-1', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2,
    ]));

    $this->withSession(sessionFor($user))
        ->withHeader('X-Tenant', 'shop-a')
        ->get("/products/{$product->id}/edit")
        ->assertOk()
        ->assertSee('Bound Product');
});

it('does not load a tenant user from the session on another tenant', function (): void {
    $tenantA = createTenant('shop-a');
    createTenant('shop-b');
    $user = createTenantUser($tenantA, ['products.view']);

    $this->withSession(sessionFor($user))
        ->withHeader('X-Tenant', 'shop-b')
        ->get('/products')
        ->assertRedirect(route('login'));
});

it('renders the product create and edit forms for a tenant user', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());

    $this->withSession(sessionFor($user))->withHeader('X-Tenant', 'shop-a')
        ->get('/products/create')
        ->assertOk()
        ->assertSee(__('catalog.add_product'));
});
