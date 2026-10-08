<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\People\Models\Customer;
use App\Domain\Purchasing\Models\Supplier;
use App\Support\Tenancy\TenantContext;

function pricedProducts($tenant, array $prices): void
{
    app(TenantContext::class)->run($tenant, function () use ($prices): void {
        foreach ($prices as $name => $price) {
            Product::create([
                'name' => $name, 'sku' => strtoupper($name).'-1', 'type' => 'standard',
                'cost_price' => 100, 'selling_price' => $price, 'tax_rate' => 0, 'tax_inclusive' => true,
            ]);
        }
    });
}

/** Position of $needle in the page, to compare row order. */
function posOf(string $html, string $needle): int
{
    $pos = strpos($html, $needle);

    return $pos === false ? -1 : $pos;
}

it('sorts the product list by price in both directions', function (): void {
    $tenant = createTenant('shop-s1');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    pricedProducts($tenant, ['Cheap' => 100, 'Mid' => 500, 'Dear' => 900]);

    $asc = $this->actingAs($user)->withHeader('X-Tenant', 'shop-s1')->get('/products?sort=price&dir=asc')->assertOk()->getContent();
    expect(posOf($asc, 'Cheap'))->toBeLessThan(posOf($asc, 'Mid'))
        ->and(posOf($asc, 'Mid'))->toBeLessThan(posOf($asc, 'Dear'));

    $desc = $this->actingAs($user)->withHeader('X-Tenant', 'shop-s1')->get('/products?sort=price&dir=desc')->assertOk()->getContent();
    expect(posOf($desc, 'Dear'))->toBeLessThan(posOf($desc, 'Mid'))
        ->and(posOf($desc, 'Mid'))->toBeLessThan(posOf($desc, 'Cheap'));
});

it('ignores a sort column that is not whitelisted', function (): void {
    $tenant = createTenant('shop-s2');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    pricedProducts($tenant, ['Alpha' => 100, 'Beta' => 200]);

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-s2')->get('/products?sort=cost_price;drop%20table%20products&dir=sideways')->assertOk()->getContent();
    expect(posOf($html, 'Alpha'))->toBeLessThan(posOf($html, 'Beta'));
});

it('keeps the search and filters when sorting and marks the active column', function (): void {
    $tenant = createTenant('shop-s3');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    pricedProducts($tenant, ['Soap' => 100]);

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-s3')->get('/products?q=soap&sort=price&dir=asc')->assertOk()->getContent();
    expect($html)->toContain('aria-sort="ascending"')->toContain('sort=price&amp;dir=desc')->toContain('q=soap');
});

it('sorts expenses by amount', function (): void {
    $tenant = createTenant('shop-s4', 'medium');
    $user = createTenantUser($tenant, ['expenses.view']);
    addExpense($tenant, 'Rent', 900000, extra: ['note' => 'big-one']);
    addExpense($tenant, 'Rent', 100000, extra: ['note' => 'small-one']);

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-s4')->get('/expenses?sort=amount&dir=asc')->assertOk()->getContent();
    expect(posOf($html, 'small-one'))->toBeLessThan(posOf($html, 'big-one'));
});

it('finds records across areas, but only those the user may see', function (): void {
    $tenant = createTenant('shop-s5');
    $user = createTenantUser($tenant, ['products.view']);
    pricedProducts($tenant, ['Mango' => 100]);
    app(TenantContext::class)->run($tenant, fn () => Customer::create(['name' => 'Mango Mama']));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-s5')->get('/search?q=mango')
        ->assertOk()->assertSee('Mango')->assertSee('MANGO-1')->assertDontSee('Mango Mama');
});

it('searches customers and suppliers for users with those permissions', function (): void {
    $tenant = createTenant('shop-s6', 'medium');
    $user = createTenantUser($tenant, ['customers.view', 'suppliers.view']);
    app(TenantContext::class)->run($tenant, function (): void {
        Customer::create(['name' => 'Zawadi Client']);
        Supplier::create(['name' => 'Zawadi Wholesale']);
    });

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-s6')->get('/search?q=zawadi')
        ->assertOk()->assertSee('Zawadi Client')->assertSee('Zawadi Wholesale');
});

it('does not search purchasing areas on a plan without them', function (): void {
    $tenant = createTenant('shop-s7', 'basic');
    $user = createTenantUser($tenant, ['suppliers.view']);
    app(TenantContext::class)->run($tenant, fn () => Supplier::create(['name' => 'Hidden Supplier']));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-s7')->get('/search?q=hidden')->assertOk()->assertDontSee('Hidden Supplier');
});

it('never returns another shop\'s records from the navbar search', function (): void {
    $tenant = createTenant('shop-s8');
    $other = createTenant('shop-s8b');
    $user = createTenantUser($tenant, ['products.view']);
    pricedProducts($other, ['Secretfruit' => 100]);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-s8')->get('/search?q=secretfruit')->assertOk()->assertDontSee('Secretfruit');
});

it('asks for at least two characters', function (): void {
    $tenant = createTenant('shop-s9');
    $user = createTenantUser($tenant, ['products.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-s9')->get('/search?q=a')->assertOk()->assertSee('Type at least 2 characters.');
});

it('puts a search box in the navbar for shop users', function (): void {
    $tenant = createTenant('shop-sa');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-sa')->get('/dashboard')->assertOk()->assertSee('action="'.route('search').'"', false);
});
