<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Actions\CreateStockTransferAction;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

function lotShop(): array
{
    $tenant = createTenant('shop-a');
    $tenant->update(['settings' => ['features' => ['batch_tracking' => true, 'expiry_tracking' => true]]]);
    $user = createTenantUser($tenant, array_merge(inventoryAdminPermissions(), ['purchases.view', 'purchases.manage', 'suppliers.view', 'suppliers.manage', 'pos.access', 'sales.view', 'sales.manage', 'customers.view']));

    return [$tenant, $user];
}

function lotProduct(Tenant $tenant, string $sku, bool $batch, bool $expiry): Product
{
    return app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => 'Item '.$sku, 'sku' => $sku, 'type' => 'standard', 'cost_price' => 1000, 'selling_price' => 2000,
        'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true, 'track_batch' => $batch, 'track_expiry' => $expiry,
    ]));
}

function lotWarehouse(Tenant $tenant, string $code = 'MAIN'): Warehouse
{
    return app(TenantContext::class)->run($tenant, fn () => Warehouse::create(['name' => $code, 'code' => $code, 'is_default' => $code === 'MAIN']));
}

/** @param  array{number?: string|null, expiry?: string|null}|null  $lot */
function receiveLot(Tenant $tenant, Product $product, Warehouse $warehouse, float $qty, ?array $lot, string $type = StockMovement::PURCHASE): void
{
    app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->move($product, $warehouse, $qty, $type, unitCost: 1000, lot: $lot));
}

function lotSell(Tenant $tenant, Product $product, Warehouse $warehouse, float $qty): void
{
    app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->move($product, $warehouse, -$qty, StockMovement::SALE));
}

/** @return array<string, float> remaining quantity per batch number */
function remainingByLot(Tenant $tenant, Product $product): array
{
    return app(TenantContext::class)->run($tenant, fn () => StockBatch::query()->where('product_id', $product->id)->orderBy('id')->get()
        ->mapWithKeys(fn (StockBatch $b): array => [($b->batch_number ?? 'none').'@'.($b->expiry_date?->format('Y-m-d') ?? '-') => (float) $b->quantity_remaining])->all());
}

it('keeps batch number and expiry only for products that track them', function (): void {
    [$tenant] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $both = lotProduct($tenant, 'BOTH', true, true);
    $none = lotProduct($tenant, 'NONE', false, false);

    receiveLot($tenant, $both, $warehouse, 5, ['number' => 'L-1', 'expiry' => '2027-03-01']);
    receiveLot($tenant, $none, $warehouse, 5, ['number' => 'L-1', 'expiry' => '2027-03-01']);

    expect(remainingByLot($tenant, $both))->toBe(['L-1@2027-03-01' => 5.0])
        ->and(remainingByLot($tenant, $none))->toBe(['none@-' => 5.0]);
});

it('sells the batch that expires first, even when it arrived later', function (): void {
    [$tenant] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'MILK', true, true);

    receiveLot($tenant, $product, $warehouse, 5, ['number' => 'A', 'expiry' => '2027-06-01']);
    receiveLot($tenant, $product, $warehouse, 5, ['number' => 'B', 'expiry' => '2027-02-01']);
    receiveLot($tenant, $product, $warehouse, 5, ['number' => 'C', 'expiry' => null]);
    lotSell($tenant, $product, $warehouse, 7);

    expect(remainingByLot($tenant, $product))->toBe(['A@2027-06-01' => 3.0, 'B@2027-02-01' => 0.0, 'C@-' => 5.0]);
});

it('keeps selling the oldest delivery first for products that do not track expiry', function (): void {
    [$tenant] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'RICE', true, false);

    receiveLot($tenant, $product, $warehouse, 5, ['number' => 'A']);
    receiveLot($tenant, $product, $warehouse, 5, ['number' => 'B']);
    lotSell($tenant, $product, $warehouse, 6);

    expect(remainingByLot($tenant, $product))->toBe(['A@-' => 0.0, 'B@-' => 4.0]);
});

it('carries batch number and expiry along when stock is transferred', function (): void {
    [$tenant, $user] = lotShop();
    $from = lotWarehouse($tenant);
    $to = lotWarehouse($tenant, 'SHOP2');
    $product = lotProduct($tenant, 'MILK', true, true);
    receiveLot($tenant, $product, $from, 5, ['number' => 'A', 'expiry' => '2027-06-01']);

    app(TenantContext::class)->run($tenant, fn () => app(CreateStockTransferAction::class)->handle([
        'idempotency_key' => 'transfer-key-1', 'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ], $user));

    $moved = app(TenantContext::class)->run($tenant, fn () => StockBatch::query()->where('warehouse_id', $to->id)->first());
    expect($moved->batch_number)->toBe('A')->and($moved->expiry_date->format('Y-m-d'))->toBe('2027-06-01');
});

it('records a purchase line with its batch and expiry, and keeps different lots of one product apart', function (): void {
    [$tenant, $user] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'MILK', true, true);
    $supplier = app(TenantContext::class)->run($tenant, fn () => Supplier::create(['name' => 'Dairy Ltd']));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/purchases', [
        'idempotency_key' => 'purchase-key-1', 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 1000, 'batch_number' => 'A', 'expiry_date' => '2027-02-01'],
            ['product_id' => $product->id, 'quantity' => 6, 'unit_cost' => 1000, 'batch_number' => 'B', 'expiry_date' => '2027-05-01'],
            ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 1000, 'batch_number' => 'A', 'expiry_date' => '2027-02-01'],
        ],
    ])->assertSessionHasNoErrors();

    expect(remainingByLot($tenant, $product))->toBe(['A@2027-02-01' => 6.0, 'B@2027-05-01' => 6.0]);
    $purchase = app(TenantContext::class)->run($tenant, fn () => Purchase::query()->with('items')->firstOrFail());
    expect($purchase->items)->toHaveCount(2)->and($purchase->items->first()->expiry_date->format('Y-m-d'))->toBe('2027-02-01');
});

it('rejects a malformed expiry date on a purchase', function (): void {
    [$tenant, $user] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'MILK', true, true);
    $supplier = app(TenantContext::class)->run($tenant, fn () => Supplier::create(['name' => 'Dairy Ltd']));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/purchases', [
        'idempotency_key' => 'purchase-key-2', 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1000, 'expiry_date' => '01/02/2027']],
    ])->assertSessionHasErrors('items.0.expiry_date');
});

it('stores the opening stock batch and expiry when a product is created', function (): void {
    [$tenant, $user] = lotShop();
    $warehouse = lotWarehouse($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/products', productPayload([
        'sku' => 'YOG-1', 'name' => 'Yogurt', 'track_batch' => 1, 'track_expiry' => 1,
        'warehouse_id' => $warehouse->id, 'opening_quantity' => 8, 'opening_batch_number' => 'Y-9', 'opening_expiry_date' => '2027-04-15',
    ]))->assertSessionHasNoErrors();

    $product = app(TenantContext::class)->run($tenant, fn () => Product::query()->where('sku', 'YOG-1')->firstOrFail());
    expect(remainingByLot($tenant, $product))->toBe(['Y-9@2027-04-15' => 8.0]);
});

it('adds a batch and expiry through a stock adjustment', function (): void {
    [$tenant, $user] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'MILK', true, true);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/stock-adjustments', [
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'direction' => 'in', 'quantity' => 3, 'reason' => 'Found',
        'batch_number' => 'F-1', 'expiry_date' => '2027-07-01',
    ])->assertSessionHasNoErrors();

    expect(remainingByLot($tenant, $product))->toBe(['F-1@2027-07-01' => 3.0]);
});

it('lists batches with their expiry and filters expired and soon-to-expire stock', function (): void {
    [$tenant, $user] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'MILK', true, true);
    receiveLot($tenant, $product, $warehouse, 1, ['number' => 'OLD', 'expiry' => now()->subDays(3)->toDateString()]);
    receiveLot($tenant, $product, $warehouse, 1, ['number' => 'SOON', 'expiry' => now()->addDays(10)->toDateString()]);
    receiveLot($tenant, $product, $warehouse, 1, ['number' => 'LATER', 'expiry' => now()->addDays(200)->toDateString()]);

    $get = fn (?string $filter) => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/stock/batches'.($filter ? '?expiry='.$filter : ''))->assertOk();

    $get(null)->assertSee('OLD')->assertSee('SOON')->assertSee('LATER');
    $get('expired')->assertSee('OLD')->assertDontSee('SOON')->assertDontSee('LATER');
    $get('soon')->assertSee('SOON')->assertDontSee('OLD')->assertDontSee('LATER');
});

it('tells the till when the oldest stock of an expiry product goes off', function (): void {
    [$tenant, $user] = lotShop();
    $warehouse = lotWarehouse($tenant);
    $product = lotProduct($tenant, 'MILK', true, true);
    $plain = lotProduct($tenant, 'SALT', false, false);
    receiveLot($tenant, $product, $warehouse, 2, ['number' => 'B', 'expiry' => '2027-02-01']);
    receiveLot($tenant, $product, $warehouse, 2, ['number' => 'A', 'expiry' => '2027-01-10']);
    receiveLot($tenant, $plain, $warehouse, 2, null);

    $rows = collect($this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->getJson('/pos/products?warehouse='.$warehouse->id)->assertOk()->json('data'))->keyBy('sku');

    expect($rows['MILK']['expiry'])->toBe('2027-01-10')->and($rows['SALT']['expiry'])->toBeNull();
});
