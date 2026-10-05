<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Support\Tenancy\TenantContext;

function purchasingPermissions(): array
{
    return ['purchases.view', 'purchases.manage', 'suppliers.view', 'suppliers.manage'];
}

function makeSupplier($tenant, string $name = 'Acme Supplies'): Supplier
{
    return app(TenantContext::class)->run($tenant, fn () => Supplier::create(['name' => $name]));
}

function receiveGoods($test, string $slug, $user, array $overrides = [])
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/purchases', $overrides);
}

function purchaseForm($tenant, Product $product, $warehouse, Supplier $supplier, array $extra = []): array
{
    return array_merge([
        'idempotency_key' => 'pk-'.uniqid(),
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => '600.00']],
    ], $extra);
}

it('receives goods: stock comes in, totals are computed on the server and the cost can be updated', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier, ['update_cost' => '1']))
        ->assertSessionHasNoErrors()->assertRedirect();

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        $purchase = Purchase::query()->firstOrFail();

        expect($purchase->number)->toBe('PU-000001')
            ->and($purchase->total)->toBe(300000)
            ->and($purchase->balance_due)->toBe(300000)
            ->and($purchase->payment_status)->toBe('unpaid')
            ->and(app(StockService::class)->quantity($product, $warehouse))->toBe(15.0)
            ->and($product->fresh()->cost_price)->toBe(60000);
    });
});

it('leaves the cost price alone unless asked', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier))->assertSessionHasNoErrors();

    app(TenantContext::class)->run($tenant, fn () => expect($product->fresh()->cost_price)->toBe(50000));
});

it('ignores a repeated submit with the same idempotency key', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);
    $form = purchaseForm($tenant, $product, $warehouse, $supplier);

    receiveGoods($this, 'shop-a', $user, $form);
    receiveGoods($this, 'shop-a', $user, $form);

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        expect(Purchase::query()->count())->toBe(1)
            ->and(app(StockService::class)->quantity($product, $warehouse))->toBe(15.0);
    });
});

it('records a payment made now and later payments until settled', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier, [
        'payment_method' => 'cash', 'payment_amount' => '1000.00',
    ]))->assertSessionHasNoErrors();

    $purchase = app(TenantContext::class)->run($tenant, fn () => Purchase::query()->firstOrFail());
    expect($purchase->payment_status)->toBe('partial')->and($purchase->balance_due)->toBe(200000);

    $pay = fn (string $amount) => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post("/purchases/{$purchase->id}/payments", ['method' => 'bank_transfer', 'amount' => $amount]);

    $pay('2500.00')->assertSessionHasErrors('amount');
    $pay('2000.00')->assertSessionHasNoErrors();
    $pay('1.00')->assertSessionHasErrors('amount');

    expect($purchase->fresh()->payment_status)->toBe('paid')->and($purchase->fresh()->balance_due)->toBe(0);
});

it('rejects overpaying a purchase and a payment without a method', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier, [
        'payment_method' => 'cash', 'payment_amount' => '9999.00',
    ]))->assertSessionHasErrors('payments');

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier, [
        'payment_amount' => '10.00',
    ]))->assertSessionHasErrors('payment_method');

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        expect(Purchase::query()->count())->toBe(0)
            ->and(app(StockService::class)->quantity($product, $warehouse))->toBe(10.0);
    });
});

it('merges a repeated product and refuses it with conflicting costs', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier, [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => '600.00'],
            ['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => '600.00'],
        ],
    ]))->assertSessionHasNoErrors();

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier, [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => '600.00'],
            ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => '700.00'],
        ],
    ]))->assertSessionHasErrors('items');

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        expect(Purchase::query()->count())->toBe(1)
            ->and(Purchase::query()->firstOrFail()->items()->count())->toBe(1)
            ->and(app(StockService::class)->quantity($product, $warehouse))->toBe(15.0);
    });
});

it('keeps purchases and suppliers isolated between tenants', function (): void {
    $a = createTenant('shop-a');
    $b = createTenant('shop-b');
    $userA = createTenantUser($a, purchasingPermissions());
    $userB = createTenantUser($b, purchasingPermissions());
    [$productA, $warehouseA] = posFixture($a);
    [, $warehouseB] = posFixture($b);
    $supplierA = makeSupplier($a);

    receiveGoods($this, 'shop-a', $userA, purchaseForm($a, $productA, $warehouseA, $supplierA))->assertSessionHasNoErrors();
    $purchase = app(TenantContext::class)->run($a, fn () => Purchase::query()->firstOrFail());

    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')->get("/purchases/{$purchase->id}")->assertNotFound();

    // Tenant B cannot buy from tenant A's supplier or receive tenant A's product.
    receiveGoods($this, 'shop-b', $userB, purchaseForm($b, $productA, $warehouseB, $supplierA))
        ->assertSessionHasErrors(['supplier_id', 'items.0.product_id']);
});

it('enforces purchasing permissions', function (): void {
    $tenant = createTenant('shop-a');
    $nobody = createTenantUser($tenant, []);
    $viewer = createTenantUser($tenant, ['purchases.view']);
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);
    $get = fn ($user, string $url) => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get($url);

    $get($nobody, '/purchases')->assertForbidden();
    $get($viewer, '/purchases')->assertOk();
    $get($viewer, '/purchases/create')->assertForbidden();
    receiveGoods($this, 'shop-a', $viewer, purchaseForm($tenant, $product, $warehouse, $supplier))->assertForbidden();
    $get($viewer, '/suppliers')->assertForbidden();
});

it('renders the purchasing pages and manages suppliers', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);
    $get = fn (string $url) => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get($url);

    receiveGoods($this, 'shop-a', $user, purchaseForm($tenant, $product, $warehouse, $supplier));
    $purchase = app(TenantContext::class)->run($tenant, fn () => Purchase::query()->firstOrFail());

    $get('/purchases')->assertOk()->assertSee('PU-000001');
    $get('/purchases/create')->assertOk()->assertSee('Acme Supplies');
    $get("/purchases/{$purchase->id}")->assertOk()->assertSee('PU-000001');
    $get('/suppliers')->assertOk()->assertSee('Acme Supplies');

    // A supplier with purchases cannot be deleted.
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->delete("/suppliers/{$supplier->id}")->assertSessionHasErrors();
    app(TenantContext::class)->run($tenant, fn () => expect(Supplier::query()->count())->toBe(1));
});
