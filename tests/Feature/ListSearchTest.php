<?php

it('searches as you type on the paginated lists', function (string $path, array $permissions) {
    $tenant = createTenant('search-shop');
    $user = createTenantUser($tenant, $permissions);

    $this->actingAs($user)->withHeader('X-Tenant', 'search-shop')->get($path)
        ->assertOk()
        ->assertSee('data-live-search', false)
        ->assertSee('fetch(url', false);
})->with([
    'products' => ['/products', ['products.view']],
    'sales' => ['/sales', ['sales.view']],
    'stock' => ['/stock', ['inventory.view']],
]);

it('keeps the other filters in the URL when searching', function () {
    $tenant = createTenant('filter-shop');
    $user = createTenantUser($tenant, ['products.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'filter-shop')->get('/products?category=abc&q=rice')
        ->assertSee('name="category" value="abc"', false)
        ->assertSee('name="q" value="rice"', false);
});
