<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/** @return array<string, mixed> */
function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rice 1kg',
        'sku' => 'RICE-1KG',
        'type' => 'standard',
        'cost_price' => '1200.50',
        'selling_price' => '1500',
        'tax_rate' => '18',
        'tax_inclusive' => 1,
        'track_stock' => 1,
        'alert_quantity' => 5,
        'is_weighed' => 0,
        'track_batch' => 0,
        'track_expiry' => 0,
        'is_active' => 1,
    ], $overrides);
}

function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $callback);
}

it('creates a product, stores prices as integer minor units and records opening stock', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    $warehouse = inTenant($tenant, fn () => Warehouse::create(['name' => 'Main', 'code' => 'MAIN']));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/products', productPayload(['warehouse_id' => $warehouse->id, 'opening_quantity' => 10]))
        ->assertRedirect(route('products.index'));

    $product = inTenant($tenant, fn () => Product::query()->firstOrFail());

    expect($product->cost_price)->toBe(120050)
        ->and($product->selling_price)->toBe(150000)
        ->and($product->tenant_id)->toBe($tenant->getKey());

    inTenant($tenant, function () use ($product): void {
        expect((float) ProductStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(10.0)
            ->and(StockMovement::query()->where('product_id', $product->id)->value('type'))->toBe(StockMovement::OPENING);
    });
});

it('rejects a category that belongs to another tenant', function (): void {
    $tenantA = createTenant('shop-a');
    $tenantB = createTenant('shop-b');
    $user = createTenantUser($tenantA, inventoryAdminPermissions());
    $foreign = inTenant($tenantB, fn () => Category::create(['name' => 'Foreign', 'slug' => 'foreign']));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/products', productPayload(['category_id' => $foreign->id]))
        ->assertSessionHasErrors('category_id');

    expect(inTenant($tenantA, fn () => Product::query()->count()))->toBe(0);
});

it('hides and protects products of other tenants', function (): void {
    $tenantA = createTenant('shop-a');
    $tenantB = createTenant('shop-b');
    $userB = createTenantUser($tenantB, inventoryAdminPermissions());
    $product = inTenant($tenantA, fn () => Product::create([
        'name' => 'Secret A', 'sku' => 'A-1', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2,
    ]));

    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')
        ->get('/products')->assertOk()->assertDontSee('Secret A');

    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')
        ->get("/products/{$product->id}/edit")->assertNotFound();

    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')
        ->delete("/products/{$product->id}")->assertNotFound();

    expect(inTenant($tenantA, fn () => Product::query()->count()))->toBe(1);
});

it('lets the same SKU exist in two different tenants but not twice in one', function (): void {
    $tenantA = createTenant('shop-a');
    $tenantB = createTenant('shop-b');
    $userA = createTenantUser($tenantA, inventoryAdminPermissions());
    $userB = createTenantUser($tenantB, inventoryAdminPermissions());

    $this->actingAs($userA)->withHeader('X-Tenant', 'shop-a')->post('/products', productPayload())->assertRedirect();
    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')->post('/products', productPayload())->assertRedirect();
    $this->actingAs($userA)->withHeader('X-Tenant', 'shop-a')->post('/products', productPayload())->assertSessionHasErrors('sku');
});

it('forbids users without the manage permission from creating products', function (): void {
    $tenant = createTenant('shop-a');
    $viewer = createTenantUser($tenant, ['products.view']);

    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-a')->get('/products')->assertOk();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-a')->post('/products', productPayload())->assertForbidden();
});

it('ignores optional behaviours that the tenant has not enabled', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/products', productPayload(['is_weighed' => 1, 'track_batch' => 1, 'track_expiry' => 1]))
        ->assertRedirect();

    $product = inTenant($tenant, fn () => Product::query()->firstOrFail());
    expect($product->is_weighed)->toBeFalse()
        ->and($product->track_batch)->toBeFalse()
        ->and($product->track_expiry)->toBeFalse();

    $tenant->update(['settings' => ['features' => ['weighed_products' => true, 'batch_tracking' => true]]]);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/products', productPayload(['sku' => 'B-2', 'is_weighed' => 1, 'track_batch' => 1, 'track_expiry' => 1]))
        ->assertRedirect();

    $second = inTenant($tenant->fresh(), fn () => Product::query()->where('sku', 'B-2')->firstOrFail());
    expect($second->is_weighed)->toBeTrue()
        ->and($second->track_batch)->toBeTrue()
        ->and($second->track_expiry)->toBeFalse();
});

it('never tracks stock for service products', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/products', productPayload(['type' => 'service', 'track_stock' => 1, 'alert_quantity' => 9]))
        ->assertRedirect();

    $product = inTenant($tenant, fn () => Product::query()->firstOrFail());
    expect($product->track_stock)->toBeFalse()->and((float) $product->alert_quantity)->toBe(0.0);
});
