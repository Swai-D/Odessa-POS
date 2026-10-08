<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\People\Models\Customer;
use App\Support\Tenancy\TenantContext;

function pickerProduct($tenant, string $name, string $sku, bool $tracked = true, bool $active = true): void
{
    app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => $name, 'sku' => $sku, 'type' => 'standard', 'cost_price' => 150000, 'selling_price' => 200000,
        'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => $tracked, 'is_active' => $active,
    ]));
}

it('searches products for pickers by name or sku, active only, within the shop', function (): void {
    $tenant = createTenant('shop-k1');
    $other = createTenant('shop-k2');
    $user = createTenantUser($tenant, ['products.view']);
    pickerProduct($tenant, 'Maize flour', 'MF-1');
    pickerProduct($tenant, 'Rice', 'RC-1');
    pickerProduct($tenant, 'Old maize', 'OM-1', active: false);
    pickerProduct($other, 'Maize secret', 'MS-1');

    $json = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k1')->getJson('/products/lookup?q=MAIZE')->assertOk()->json('data');

    expect(array_column($json, 'label'))->toBe(['Maize flour (MF-1)'])
        ->and($json[0]['cost'])->toBe('1500.00');
});

it('can limit the picker to stock-tracked products', function (): void {
    $tenant = createTenant('shop-k3');
    $user = createTenantUser($tenant, ['products.view']);
    pickerProduct($tenant, 'Counted', 'C-1');
    pickerProduct($tenant, 'Service', 'S-1', tracked: false);

    $all = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k3')->getJson('/products/lookup')->json('data');
    expect($all)->toHaveCount(2);
});

it('limits tracked lookups to counted products', function (): void {
    $tenant = createTenant('shop-k4');
    $user = createTenantUser($tenant, ['products.view']);
    pickerProduct($tenant, 'Counted', 'C-1');
    pickerProduct($tenant, 'Service', 'S-1', tracked: false);

    $tracked = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k4')->getJson('/products/lookup?tracked=1')->json('data');
    expect(array_column($tracked, 'label'))->toBe(['Counted (C-1)']);
});

it('refuses the product lookup without permission', function (): void {
    $tenant = createTenant('shop-k5');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-k5')->getJson('/products/lookup')->assertForbidden();
});

it('does not embed the whole catalogue in the adjustment and purchase forms', function (): void {
    $tenant = createTenant('shop-k6');
    $user = createTenantUser($tenant, [...inventoryAdminPermissions(), 'purchases.view', 'purchases.manage', 'suppliers.view']);
    pickerProduct($tenant, 'Needle in haystack', 'NH-1');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-k6')->get('/stock-adjustments')->assertOk()
        ->assertDontSee('Needle in haystack')->assertSee('js-product-picker', false);
});

it('does not embed the whole catalogue in the purchase form', function (): void {
    $tenant = createTenant('shop-k7');
    $user = createTenantUser($tenant, ['purchases.view', 'purchases.manage', 'suppliers.view']);
    pickerProduct($tenant, 'Needle in haystack', 'NH-1');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-k7')->get('/purchases/create')->assertOk()
        ->assertDontSee('Needle in haystack');
});

it('searches customers for the till by name or phone and restores one by id', function (): void {
    $tenant = createTenant('shop-k8');
    $other = createTenant('shop-k9');
    $user = createTenantUser($tenant, posPermissions());
    [$mine, $theirs] = [
        app(TenantContext::class)->run($tenant, fn () => Customer::create(['name' => 'Asha Mwinyi', 'phone' => '0712000111'])),
        app(TenantContext::class)->run($other, fn () => Customer::create(['name' => 'Asha Hidden', 'phone' => '0712999999'])),
    ];

    $byName = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k8')->getJson('/pos/customers?q=asha')->assertOk()->json('data');
    expect(array_column($byName, 'name'))->toBe(['Asha Mwinyi']);

    $byPhone = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k8')->getJson('/pos/customers?q=0712000')->json('data');
    expect($byPhone)->toHaveCount(1);

    $byId = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k8')->getJson('/pos/customers?id='.$mine->id)->json('data');
    expect($byId[0]['id'])->toBe($mine->id);

    $foreign = $this->actingAs($user)->withHeader('X-Tenant', 'shop-k8')->getJson('/pos/customers?id='.$theirs->id)->json('data');
    expect($foreign)->toBe([]);
});

it('loads only a few customers into the till and has no template leftovers', function (): void {
    $tenant = createTenant('shop-ka');
    $user = createTenantUser($tenant, posPermissions());
    app(TenantContext::class)->run($tenant, function (): void {
        for ($i = 1; $i <= 30; $i++) {
            Customer::create(['name' => sprintf('Buyer %02d', $i)]);
        }
    });

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-ka')->get('/pos')->assertOk()->getContent();

    expect($html)->toContain('Buyer 20')->not->toContain('Buyer 21')
        ->and($html)->not->toContain('Grocery Eden')
        ->and($html)->not->toContain('index.html')
        ->and($html)->not->toContain('ui-accordion.html')
        ->and($html)->toContain('pos-customer-search');
});
