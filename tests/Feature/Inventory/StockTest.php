<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InsufficientStockException;
use App\Domain\Inventory\Services\StockService;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

function stockFixture(Tenant $tenant): array
{
    return app(TenantContext::class)->run($tenant, fn () => [
        Product::create(['name' => 'Soap', 'sku' => 'SOAP', 'type' => 'standard', 'cost_price' => 100, 'selling_price' => 200]),
        Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]),
    ]);
}

it('keeps a ledger and running balance for every stock change', function (): void {
    $tenant = createTenant('shop-a');
    [$product, $warehouse] = stockFixture($tenant);

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        $service = app(StockService::class);
        $service->move($product, $warehouse, 10, StockMovement::OPENING);
        $out = $service->move($product, $warehouse, -3.5, StockMovement::ADJUSTMENT_OUT, 'damaged');

        expect((float) $out->balance_after)->toBe(6.5)
            ->and($service->quantity($product, $warehouse))->toBe(6.5)
            ->and(StockMovement::query()->count())->toBe(2);
    });
});

it('refuses to move stock below zero and leaves balances untouched', function (): void {
    $tenant = createTenant('shop-a');
    [$product, $warehouse] = stockFixture($tenant);

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        $service = app(StockService::class);
        $service->move($product, $warehouse, 2, StockMovement::OPENING);

        expect(fn () => $service->move($product, $warehouse, -5, StockMovement::ADJUSTMENT_OUT, 'oops'))
            ->toThrow(InsufficientStockException::class);

        expect($service->quantity($product, $warehouse))->toBe(2.0)
            ->and(StockMovement::query()->count())->toBe(1);
    });
});

it('shows a validation error instead of negative stock when adjusting through the UI', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    [$product, $warehouse] = stockFixture($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/stock-adjustments', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'direction' => 'out', 'quantity' => 1, 'reason' => 'test',
        ])
        ->assertSessionHasErrors('quantity');

    expect(app(TenantContext::class)->run($tenant, fn () => StockMovement::query()->count()))->toBe(0);
});

it('rejects adjustments that use another tenants product or warehouse', function (): void {
    $tenantA = createTenant('shop-a');
    $tenantB = createTenant('shop-b');
    $userA = createTenantUser($tenantA, inventoryAdminPermissions());
    [$foreignProduct, $foreignWarehouse] = stockFixture($tenantB);

    $this->actingAs($userA)->withHeader('X-Tenant', 'shop-a')
        ->post('/stock-adjustments', [
            'product_id' => $foreignProduct->id, 'warehouse_id' => $foreignWarehouse->id,
            'direction' => 'in', 'quantity' => 5, 'reason' => 'attack',
        ])
        ->assertSessionHasErrors(['product_id', 'warehouse_id']);

    expect(app(TenantContext::class)->run($tenantB, fn () => ProductStock::query()->count()))->toBe(0);
});

it('does not leak stock levels across tenants', function (): void {
    $tenantA = createTenant('shop-a');
    $tenantB = createTenant('shop-b');
    $userB = createTenantUser($tenantB, inventoryAdminPermissions());
    [$product, $warehouse] = stockFixture($tenantA);
    app(TenantContext::class)->run($tenantA, fn () => app(StockService::class)->move($product, $warehouse, 7, StockMovement::OPENING));

    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')->get('/stock')->assertOk()->assertDontSee('Soap');
});

it('keeps a single default warehouse and blocks deleting one with stock history', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, inventoryAdminPermissions());
    [$product, $main] = stockFixture($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post('/warehouses', ['name' => 'Branch', 'code' => 'BR1', 'is_default' => 1, 'is_active' => 1])
        ->assertRedirect();

    app(TenantContext::class)->run($tenant, function () use ($main): void {
        expect(Warehouse::query()->where('is_default', true)->count())->toBe(1)
            ->and($main->fresh()->is_default)->toBeFalse();
    });

    app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->move($product, $main, 1, StockMovement::OPENING));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->delete("/warehouses/{$main->id}")
        ->assertSessionHasErrors('delete');
});
