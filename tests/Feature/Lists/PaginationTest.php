<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\People\Models\Customer;
use App\Support\Tenancy\TenantContext;

function listProducts($tenant, int $count, string $prefix = 'Item'): void
{
    app(TenantContext::class)->run($tenant, function () use ($count, $prefix): void {
        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'name' => sprintf('%s %02d', $prefix, $i), 'sku' => sprintf('%s-%02d', strtoupper($prefix), $i), 'type' => 'standard',
                'cost_price' => 100, 'selling_price' => 200, 'tax_rate' => 0, 'tax_inclusive' => true,
            ]);
        }
    });
}

it('pages the product list instead of loading everything', function (): void {
    $tenant = createTenant('shop-pg');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    listProducts($tenant, 30);

    $first = $this->actingAs($user)->withHeader('X-Tenant', 'shop-pg')->get('/products')->assertOk()->getContent();
    expect($first)->toContain('Item 01')->toContain('Item 25')->not->toContain('Item 26')
        ->toContain('page=2');
});

it('shows the rest on page two', function (): void {
    $tenant = createTenant('shop-p2');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    listProducts($tenant, 30);

    $second = $this->actingAs($user)->withHeader('X-Tenant', 'shop-p2')->get('/products?page=2')->assertOk()->getContent();
    expect($second)->toContain('Item 26')->toContain('Item 30')->not->toContain('Item 01');
});

it('searches across the whole list, case-insensitively and without treating % as a wildcard', function (): void {
    $tenant = createTenant('shop-sr');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    listProducts($tenant, 30);
    listProducts($tenant, 1, 'Zebra');

    $found = $this->actingAs($user)->withHeader('X-Tenant', 'shop-sr')->get('/products?q=zEbRa')->assertOk()->getContent();
    expect($found)->toContain('Zebra 01')->not->toContain('Item 01');

    // Search matches the SKU too, including items beyond the first page.
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-sr')->get('/products?q=item-30')->assertSee('Item 30')->assertDontSee('Item 29');

    $wildcard = $this->actingAs($user)->withHeader('X-Tenant', 'shop-sr')->get('/products?q=%25')->assertOk()->getContent();
    expect($wildcard)->not->toContain('Item 01');
});

it('never lists another shop\'s records', function (): void {
    $a = createTenant('shop-ia');
    $b = createTenant('shop-ib');
    $user = createTenantUser($a, inventoryAdminPermissions());
    listProducts($b, 3, 'Secret');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-ia')->get('/products?q=secret')->assertOk()->assertDontSee('Secret 01');
});

it('pages and searches lookup lists such as customers', function (): void {
    $tenant = createTenant('shop-cu');
    $user = createTenantUser($tenant, ['customers.view', 'customers.manage']);
    app(TenantContext::class)->run($tenant, function (): void {
        for ($i = 1; $i <= 30; $i++) {
            Customer::create(['name' => sprintf('Client %02d', $i), 'phone' => sprintf('0700000%03d', $i)]);
        }
    });

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'shop-cu')->get('/customers')->assertOk()->getContent();
    expect($page)->toContain('Client 25')->not->toContain('Client 26');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-cu')->get('/customers?q=0700000030')->assertSee('Client 30')->assertDontSee('Client 01');
});

it('keeps the sales status filter while searching and paging', function (): void {
    $tenant = createTenant('shop-sf');
    $user = createTenantUser($tenant, ['sales.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-sf')->get('/sales?status=unpaid')->assertOk()
        ->assertSee('name="status" value="unpaid"', false)
        ->assertSee('name="q"', false);
});
