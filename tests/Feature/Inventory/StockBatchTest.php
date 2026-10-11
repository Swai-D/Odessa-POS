<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Finance\Services\ReportService;
use App\Domain\Inventory\Actions\CreateStockTransferAction;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockBatchAllocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Actions\ReturnItemsAction;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

function batchPosPermissions(): array
{
    return ['pos.access', 'sales.view', 'sales.manage', 'customers.view', 'customers.manage'];
}

function batchPurchasePermissions(): array
{
    return ['purchases.view', 'purchases.manage', 'suppliers.view', 'suppliers.manage'];
}

/** @return array{0: Product, 1: Warehouse} */
function batchPosFixture(Tenant $tenant, int $stock = 10): array
{
    return app(TenantContext::class)->run($tenant, function () use ($stock): array {
        $product = Product::create([
            'name' => 'Soap', 'sku' => 'SOAP', 'type' => 'standard', 'cost_price' => 50000,
            'selling_price' => 100000, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true,
        ]);
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        app(StockService::class)->move($product, $warehouse, $stock, StockMovement::OPENING);

        return [$product, $warehouse];
    });
}

function batchCheckout(object $test, string $slug, User $user, array $payload)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->postJson('/pos/checkout', $payload);
}

function sellBatchUnits(object $test, string $slug, User $user, Product $product, Warehouse $warehouse, int $quantity): int
{
    $payload = [
        'idempotency_key' => 'key-'.uniqid(),
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        'payments' => [['method' => 'cash', 'amount' => $quantity * 100000]],
    ];

    return batchCheckout($test, $slug, $user, $payload)->assertCreated()->json('id');
}

function batchSupplier(Tenant $tenant): Supplier
{
    return app(TenantContext::class)->run($tenant, fn (): Supplier => Supplier::create(['name' => 'Acme Supplies']));
}

function batchReceiveGoods(object $test, string $slug, User $user, array $payload)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/purchases', $payload);
}

function batchPurchaseForm(Product $product, Warehouse $warehouse, Supplier $supplier, array $extra = []): array
{
    return array_merge([
        'idempotency_key' => 'pk-'.uniqid(),
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => '600.00']],
    ], $extra);
}

/** @return array{0: Tenant, 1: User, 2: Warehouse, 3: Warehouse, 4: Product} */
function batchTransferShop(string $slug, array $permissions = ['inventory.view', 'inventory.manage'], float $onHand = 10): array
{
    $tenant = createTenant($slug, 'medium');
    $user = createTenantUser($tenant, $permissions);
    [$main, $branch, $product] = app(TenantContext::class)->run($tenant, function () use ($onHand): array {
        $main = Warehouse::create(['name' => 'Main Store', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);
        $branch = Warehouse::create(['name' => 'Branch', 'code' => 'BR', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Soap', 'sku' => 'SOAP-1', 'type' => 'standard', 'cost_price' => 100, 'selling_price' => 200,
            'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true,
        ]);
        if ($onHand > 0) {
            app(StockService::class)->move($product, $main, $onHand, StockMovement::OPENING, 'start');
        }

        return [$main, $branch, $product];
    });

    return [$tenant, $user, $main, $branch, $product];
}

function batchTransferPayload(Warehouse $from, Warehouse $to, Product $product, string|float $quantity): array
{
    return [
        'idempotency_key' => 'tr-'.bin2hex(random_bytes(6)),
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
    ];
}

/** @return list<array{remaining: float, cost: int}> batches with stock left for the product, oldest first */
function batchesOf($tenant, $product, $warehouse): array
{
    return app(TenantContext::class)->run($tenant, fn () => StockBatch::query()
        ->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)
        ->where('quantity_remaining', '>', 0)->orderBy('received_at')->orderBy('id')->get()
        ->map(fn (StockBatch $b): array => ['remaining' => (float) $b->quantity_remaining, 'cost' => $b->unit_cost])
        ->all());
}

function sellUnits($test, string $slug, $user, $product, $warehouse, int $quantity): int
{
    return checkout($test, $slug, $user, cart($product, $warehouse, [
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        'payments' => [['method' => 'cash', 'amount' => $quantity * 100000]],
    ]))->assertCreated()->json('id');
}

it('keeps the old cost on the units left when the same product is bought again at a new price', function (): void {
    $tenant = createTenant('shop-b1');
    $user = createTenantUser($tenant, array_merge(batchPosPermissions(), batchPurchasePermissions()));
    [$product, $warehouse] = batchPosFixture($tenant); // 10 units at 500.00

    sellBatchUnits($this, 'shop-b1', $user, $product, $warehouse, 8); // 2 left from the first batch

    // Restock 10 more at 600.00 and ask to update the product's reference cost too.
    batchReceiveGoods($this, 'shop-b1', $user, batchPurchaseForm($product, $warehouse, batchSupplier($tenant), [
        'update_cost' => '1',
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => '600.00']],
    ]))->assertSessionHasNoErrors();

    expect(batchesOf($tenant, $product, $warehouse))->toBe([
        ['remaining' => 2.0, 'cost' => 50000],
        ['remaining' => 10.0, 'cost' => 60000],
    ]);
    expect(app(TenantContext::class)->run($tenant, fn () => $product->fresh()->cost_price))->toBe(60000);

    // 3 units: 2 at 500.00 from the old batch and 1 at 600.00 from the new one = 1,600.00 / 3.
    $saleId = sellBatchUnits($this, 'shop-b1', $user, $product, $warehouse, 3);

    app(TenantContext::class)->run($tenant, function () use ($saleId): void {
        expect(SaleItem::query()->where('sale_id', $saleId)->firstOrFail()->cost_price)->toBe(53333);
    });

    expect(batchesOf($tenant, $product, $warehouse))->toBe([['remaining' => 9.0, 'cost' => 60000]]);

    // The 9 units left are worth what they cost: 9 x 600.00.
    $valuation = app(TenantContext::class)->run($tenant, fn () => (new ReportService(Carbon::today(), Carbon::today()))->stockValuation());
    expect($valuation['value'])->toBe(9 * 60000)->and($valuation['units'])->toBe(9.0);
});

it('does not touch the cost of stock on the shelf when the product cost price is edited', function (): void {
    $tenant = createTenant('shop-b2');
    $user = createTenantUser($tenant, batchPosPermissions());
    [$product, $warehouse] = batchPosFixture($tenant);

    app(TenantContext::class)->run($tenant, fn () => $product->update(['cost_price' => 90000]));

    $saleId = sellBatchUnits($this, 'shop-b2', $user, $product, $warehouse, 2);

    app(TenantContext::class)->run($tenant, function () use ($saleId): void {
        expect(SaleItem::query()->where('sale_id', $saleId)->firstOrFail()->cost_price)->toBe(50000);
    });
});

it('puts returned units back into the batches the sale took them from', function (): void {
    $tenant = createTenant('shop-b3');
    $user = createTenantUser($tenant, array_merge(batchPosPermissions(), batchPurchasePermissions()));
    [$product, $warehouse] = batchPosFixture($tenant);

    sellBatchUnits($this, 'shop-b3', $user, $product, $warehouse, 8);
    batchReceiveGoods($this, 'shop-b3', $user, batchPurchaseForm($product, $warehouse, batchSupplier($tenant), [
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => '600.00']],
    ]))->assertSessionHasNoErrors();

    $saleId = sellBatchUnits($this, 'shop-b3', $user, $product, $warehouse, 3); // 2 @ 500.00 + 1 @ 600.00

    app(TenantContext::class)->run($tenant, function () use ($saleId, $user): void {
        $sale = Sale::query()->findOrFail($saleId);
        $item = SaleItem::query()->where('sale_id', $saleId)->firstOrFail();

        app(ReturnItemsAction::class)->handle($sale, [
            'items' => [['sale_item_id' => $item->id, 'quantity' => 3]],
            'refund_method' => 'cash',
        ], $user);
    });

    expect(batchesOf($tenant, $product, $warehouse))->toBe([
        ['remaining' => 2.0, 'cost' => 50000],
        ['remaining' => 10.0, 'cost' => 60000],
    ]);
});

it('uses the sale line cost when returning a sale without batch allocations', function (): void {
    $tenant = createTenant('shop-b8');
    $user = createTenantUser($tenant, array_merge(batchPosPermissions(), batchPurchasePermissions()));
    [$product, $warehouse] = batchPosFixture($tenant);

    sellBatchUnits($this, 'shop-b8', $user, $product, $warehouse, 8);
    batchReceiveGoods($this, 'shop-b8', $user, batchPurchaseForm($product, $warehouse, batchSupplier($tenant), [
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => '600.00']],
    ]))->assertSessionHasNoErrors();

    $saleId = sellBatchUnits($this, 'shop-b8', $user, $product, $warehouse, 3);
    app(TenantContext::class)->run($tenant, function () use ($saleId, $product): void {
        $sale = Sale::query()->findOrFail($saleId);
        $item = SaleItem::query()->where('sale_id', $saleId)->firstOrFail();
        $movement = StockMovement::query()->where('type', StockMovement::SALE)
            ->where('reference_id', $saleId)->firstOrFail();
        StockBatchAllocation::query()->where('stock_movement_id', $movement->getKey())->delete();
        $product->update(['cost_price' => 90000]);

        app(ReturnItemsAction::class)->handle($sale, [
            'items' => [['sale_item_id' => $item->id, 'quantity' => 3]],
            'refund_method' => 'cash',
        ], null);
    });

    expect(batchesOf($tenant, $product, $warehouse))->toBe([
        ['remaining' => 9.0, 'cost' => 60000],
        ['remaining' => 3.0, 'cost' => 53333],
    ]);
});

it('carries cost and age of the batches along when stock is transferred', function (): void {
    [$tenant, $user, $main, $branch, $product] = batchTransferShop('shop-b4'); // 10 units at 1.00

    app(TenantContext::class)->run($tenant, function () use ($product, $main): void {
        app(StockService::class)->move($product, $main, 5, StockMovement::PURCHASE, 'PU-X', null, null, unitCost: 150);
    });

    app(TenantContext::class)->run($tenant, function () use ($user, $main, $branch, $product): void {
        app(CreateStockTransferAction::class)->handle(batchTransferPayload($main, $branch, $product, 12), $user);
    });

    expect(batchesOf($tenant, $product, $main))->toBe([['remaining' => 3.0, 'cost' => 150]])
        ->and(batchesOf($tenant, $product, $branch))->toBe([
            ['remaining' => 10.0, 'cost' => 100],
            ['remaining' => 2.0, 'cost' => 150],
        ]);
});

it('takes manual write-offs from the oldest batch and costs added stock at the latest known cost', function (): void {
    [$tenant, , $main, , $product] = batchTransferShop('shop-b5'); // 10 units at 1.00

    app(TenantContext::class)->run($tenant, function () use ($product, $main): void {
        $service = app(StockService::class);
        $service->move($product, $main, 5, StockMovement::PURCHASE, 'PU-X', null, null, unitCost: 150);
        $out = $service->move($product, $main, -12, StockMovement::ADJUSTMENT_OUT, 'damaged');
        $service->move($product, $main, 4, StockMovement::ADJUSTMENT_IN, 'found');

        expect($out->averageUnitCost())->toBe((int) round((10 * 100 + 2 * 150) / 12));
    });

    expect(batchesOf($tenant, $product, $main))->toBe([
        ['remaining' => 3.0, 'cost' => 150],
        ['remaining' => 4.0, 'cost' => 150],
    ]);
});

it('lists the batches still on the shelf with their cost', function (): void {
    [$tenant, $user, $main, , $product] = batchTransferShop('shop-b6', ['inventory.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-b6')->get('/stock/batches')
        ->assertOk()->assertSee('SOAP-1')->assertSee('TZS 1.00');
});

it('hides the batches page from users without inventory access', function (): void {
    [$tenant, $user] = batchTransferShop('shop-b7', ['sales.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-b7')->get('/stock/batches')->assertForbidden();
});
