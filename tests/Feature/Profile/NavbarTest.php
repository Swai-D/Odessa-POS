<?php

use App\Domain\Catalog\Models\Product;
use App\Support\Tenancy\TenantContext;

function navbarHtml($test, string $slug, $user): string
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->get('/dashboard')->assertOk()->getContent();
}

it('has no leftover template placeholders', function (): void {
    $tenant = createTenant('shop-n');
    $user = createTenantUser($tenant, ['dashboard.view', 'pos.access', 'products.manage']);

    $html = navbarHtml($this, 'shop-n', $user);

    expect($html)->not->toContain('Grocery Eden')
        ->and($html)->not->toContain('Aron Varu')
        ->and($html)->not->toContain('Andrea')
        ->and($html)->not->toContain('email.html')
        ->and($html)->not->toContain('activities.html')
        ->and($html)->not->toContain('add-product.html')
        ->and($html)->toContain(route('pos.index'))
        ->and($html)->toContain(route('products.create'));
});

it('only offers quick-add links the user may use on their plan', function (): void {
    $tenant = createTenant('shop-q', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view', 'purchases.manage', 'suppliers.manage', 'customers.manage']);

    $html = navbarHtml($this, 'shop-q', $user);

    expect($html)->toContain(route('customers.index'))
        ->and($html)->not->toContain(route('purchases.create'))
        ->and($html)->not->toContain(route('suppliers.index'))
        ->and($html)->not->toContain(route('pos.index'));
});

it('switches language from the navbar', function (): void {
    $tenant = createTenant('shop-l');
    $user = createTenantUser($tenant, ['dashboard.view']);

    expect(navbarHtml($this, 'shop-l', $user))->toContain(route('locale.switch', 'sw'));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-l')->post(route('locale.switch', 'sw'))->assertRedirect();
    expect($user->fresh()->locale)->toBe('sw');
});

it('lists real low-stock products in the bell and nothing for users who cannot see stock', function (): void {
    $tenant = createTenant('shop-b');
    $viewer = createTenantUser($tenant, ['dashboard.view', 'inventory.view']);
    $blind = createTenantUser($tenant, ['dashboard.view']);
    app(TenantContext::class)->run($tenant, function (): void {
        Product::create(['name' => 'Rice', 'sku' => 'RICE', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true, 'alert_quantity' => 5]);
        Product::create(['name' => 'Beans', 'sku' => 'BEAN', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true, 'alert_quantity' => 0]);
    });

    expect(navbarHtml($this, 'shop-b', $viewer))->toContain('Rice')->not->toContain('Beans')
        ->and(navbarHtml($this, 'shop-b', $blind))->not->toContain('Rice');
});
